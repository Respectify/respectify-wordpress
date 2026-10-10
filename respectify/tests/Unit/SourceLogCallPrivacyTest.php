<?php

namespace Respectify\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Static guard: no respectify_log() or error_log() call in the plugin's own source may be handed comment
 * text, post content, commenter details, request input, or a whole comment array. This reaches code paths the
 * behavioural test does not drive (admin pages, future code). Commented-out calls are checked too, so a
 * disabled leak cannot be switched back on by uncommenting it.
 */
final class SourceLogCallPrivacyTest extends TestCase {

    private const PLUGIN_ROOT = __DIR__ . '/../..';

    /** Plugin source locations, relative to PLUGIN_ROOT. vendor/, build output and tests are not plugin source. */
    private const SOURCE_LOCATIONS = ['includes', 'admin', 'public', 'respectify.php', 'uninstall.php', 'index.php'];

    /**
     * Patterns that, inside a log call's arguments, mean private data is being logged.
     * Keyed by a human-readable reason shown on failure.
     */
    private const FORBIDDEN_ARGUMENT_PATTERNS = [
        'comment text variable' => '/\$(comment_text|full_comment_text|reply_to_comment_text)\b/',
        'post content variable' => '/\$post_content\b/',
        'commenter name/email variable' => '/\$(author_name|author_email|email)\b/',
        'feedback built from the evaluation (can quote the comment)' => '/\$feedback_html\b/',
        'whole comment data array' => '/\$(commentdata|held_comment|result)\b/',
        'comment field' => '/comment_(content|author|author_email|author_url|author_IP|agent)\b/',
        'raw request input' => '/\$_(POST|GET|REQUEST|SERVER|COOKIE)\b/',
        'dump of a whole value' => '/\b(print_r|var_export|var_dump|serialize)\s*\(/',
    ];

    /**
     * Expressions that reduce private data to safe metadata. They are removed from the argument text before
     * the forbidden patterns are checked, so `strlen($comment_text)` passes while `$comment_text` does not.
     */
    private const SAFE_METADATA_PATTERNS = [
        // Length, count, presence and type checks, and the plugin's own metadata-only helpers.
        '/\b(strlen|mb_strlen|count|empty|isset|is_array|gettype|is_wp_error|respectify_comment_log_summary|respectify_describe_exception)\s*\([^()]*\)/',
        // Integer casts of a single value, eg (int) $commentdata['comment_parent'].
        '/\(int\)\s*\$\w+(\[[^\]]*\])*/',
        // WP_Error codes are fixed identifiers, unlike messages, which can carry feedback quoting the comment.
        '/\$\w+->get_error_code\(\)/',
    ];


    public function testLogCalls_PluginSource_PassNoPrivateData(): void {
        $violations = [];
        foreach ($this->pluginSourceFiles() as $source_file) {
            foreach ($this->logCallArguments($source_file) as [$line, $arguments]) {
                $unsafe_arguments = (string) preg_replace(self::SAFE_METADATA_PATTERNS, '', $arguments);
                foreach (self::FORBIDDEN_ARGUMENT_PATTERNS as $reason => $pattern) {
                    if (preg_match($pattern, $unsafe_arguments) === 1) {
                        $violations[] = sprintf('%s:%d logs %s: %s', $this->relativePath($source_file), $line, $reason, trim($arguments));
                    }
                }
            }
        }

        $this->assertSame([], $violations, "Log calls must not receive private data:\n" . implode("\n", $violations));
    }


    public function testLogCallScan_PluginSource_FindsLogCalls(): void {
        // Guards against a vacuous pass of the test above if the scan stops matching anything.
        $log_call_count = 0;
        foreach ($this->pluginSourceFiles() as $source_file) {
            $log_call_count += count($this->logCallArguments($source_file));
        }

        $this->assertGreaterThan(20, $log_call_count, 'Scanner should find the plugin\'s log calls');
    }


    public function testErrorLogCalls_PluginSource_OnlyInsideRespectifyLog(): void {
        // respectify_log() writes only when WP_DEBUG is on; a direct error_log() call would bypass that and
        // write on production sites.
        $direct_calls = [];
        foreach ($this->pluginSourceFiles() as $source_file) {
            $tokens = \PhpToken::tokenize((string) file_get_contents($source_file));
            $enclosing_function = null;
            foreach ($tokens as $index => $token) {
                if ($token->is(T_FUNCTION)) {
                    $name_index = $this->nextSignificantIndex($tokens, $index);
                    $enclosing_function = $name_index === null ? null : $tokens[$name_index]->text;
                    continue;
                }
                if (!$token->is([T_STRING, T_NAME_FULLY_QUALIFIED]) || ltrim($token->text, '\\') !== 'error_log') {
                    continue;
                }
                $open_index = $this->nextSignificantIndex($tokens, $index);
                if ($open_index === null || $tokens[$open_index]->text !== '(' || $enclosing_function === 'respectify_log') {
                    continue;
                }
                $direct_calls[] = sprintf('%s:%d', $this->relativePath($source_file), $token->line);
            }
        }

        $this->assertSame([], $direct_calls, "Log through respectify_log(), not error_log():\n" . implode("\n", $direct_calls));
    }


    /**
     * @return list<string>
     */
    private function pluginSourceFiles(): array {
        $source_files = [];
        foreach (self::SOURCE_LOCATIONS as $location) {
            $path = self::PLUGIN_ROOT . '/' . $location;
            if (is_file($path)) {
                $source_files[] = $path;
                continue;
            }
            if (!is_dir($path)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file instanceof \SplFileInfo && $file->getExtension() === 'php') {
                    $source_files[] = $file->getPathname();
                }
            }
        }
        sort($source_files);
        return $source_files;
    }


    /**
     * Find every respectify_log()/error_log() call in a file, live or commented out, with its argument source.
     *
     * @return list<array{0: int, 1: string}> [line number, argument source text] per call.
     */
    private function logCallArguments(string $source_file): array {
        $source = (string) file_get_contents($source_file);
        $calls = [];

        $tokens = \PhpToken::tokenize($source);
        $token_count = count($tokens);
        for ($index = 0; $index < $token_count; $index++) {
            $token = $tokens[$index];

            if ($token->is([T_COMMENT, T_DOC_COMMENT])) {
                // A commented-out call is one uncomment away from running.
                foreach ($this->logCallsInText($token->text, $token->line) as $commented_call) {
                    $calls[] = $commented_call;
                }
                continue;
            }

            if (!$token->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED]) || !$this->isLogFunctionName($token->text)) {
                continue;
            }
            if ($this->previousSignificantToken($tokens, $index)?->is(T_FUNCTION)) {
                continue; // The definition of respectify_log itself, not a call.
            }

            $open_index = $this->nextSignificantIndex($tokens, $index);
            if ($open_index === null || $tokens[$open_index]->text !== '(') {
                continue;
            }

            $depth = 0;
            $arguments = '';
            for ($arg_index = $open_index; $arg_index < $token_count; $arg_index++) {
                $text = $tokens[$arg_index]->text;
                if ($text === '(') {
                    $depth++;
                } elseif ($text === ')') {
                    $depth--;
                    if ($depth === 0) {
                        break;
                    }
                }
                if ($arg_index > $open_index) {
                    $arguments .= $text;
                }
            }
            $calls[] = [$token->line, $arguments];
        }

        return $calls;
    }


    /**
     * @return list<array{0: int, 1: string}>
     */
    private function logCallsInText(string $comment_text, int $first_line): array {
        $calls = [];
        $matched = preg_match_all('/\b(?:respectify_log|error_log)\s*\((.*)$/m', $comment_text, $matches, PREG_OFFSET_CAPTURE);
        if ($matched === false) {
            return $calls;
        }
        foreach ($matches[1] as [$arguments, $offset]) {
            $calls[] = [$first_line + substr_count(substr($comment_text, 0, $offset), "\n"), $arguments];
        }
        return $calls;
    }


    private function isLogFunctionName(string $name): bool {
        $short_name = substr($name, (int) strrpos('\\' . $name, '\\'));
        return $short_name === 'respectify_log' || $short_name === 'error_log';
    }


    /**
     * @param array<int, \PhpToken> $tokens
     */
    private function previousSignificantToken(array $tokens, int $index): ?\PhpToken {
        for ($previous = $index - 1; $previous >= 0; $previous--) {
            if (!$tokens[$previous]->isIgnorable()) {
                return $tokens[$previous];
            }
        }
        return null;
    }


    /**
     * @param array<int, \PhpToken> $tokens
     */
    private function nextSignificantIndex(array $tokens, int $index): ?int {
        $token_count = count($tokens);
        for ($next = $index + 1; $next < $token_count; $next++) {
            if (!$tokens[$next]->isIgnorable()) {
                return $next;
            }
        }
        return null;
    }


    private function relativePath(string $source_file): string {
        $root = (string) realpath(self::PLUGIN_ROOT);
        $real = (string) realpath($source_file);
        return ltrim(substr($real, strlen($root)), '/');
    }
}
