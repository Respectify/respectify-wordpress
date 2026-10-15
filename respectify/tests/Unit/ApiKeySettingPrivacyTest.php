<?php

namespace Respectify\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Respectify\Tests\Support\WordPressStubState;

/**
 * The saved API key never appears in the settings page. The field is rendered empty, a saved key is shown
 * only as a note that one is saved, and saving the form with the field left blank keeps the saved key, so
 * the page never needs the key to round-trip through the browser. The credentials test falls back to the
 * saved key when the field is blank, so it still works without the key being in the page, and then only ever
 * sends the saved key to the saved server, whatever server the request names.
 */
final class ApiKeySettingPrivacyTest extends TestCase {

    private const SAVED_API_KEY = 'zqx-saved-api-key-marker-1234';
    private const NEW_API_KEY = 'zqx-new-api-key-marker-5678';
    private const SAVED_BASE_URL = 'app.respectify.example';
    private const SAVED_API_VERSION = '0.2';
    private const OTHER_BASE_URL = 'collector.attacker.example:8443';
    private const OTHER_API_VERSION = '9.9';


    protected function setUp(): void {
        WordPressStubState::reset();
        $_POST = [];
    }


    protected function tearDown(): void {
        $_POST = [];
    }


    public function testApiKeyCallback_KeySaved_DoesNotOutputKey(): void {
        $this->saveApiKey(self::SAVED_API_KEY);

        $html = self::renderApiKeyField();

        $this->assertStringNotContainsString(self::SAVED_API_KEY, $html, 'The saved API key must never be written into the page');
        $this->assertStringNotContainsString(
            WordPressStubState::$options[\Respectify\OPTION_API_KEY_ENCRYPTED],
            $html,
            'The encrypted API key must not be written into the page either'
        );
        $this->assertMatchesRegularExpression('/<input[^>]*name="respectify_api_key"[^>]*value=""/', $html, 'The API key field should be empty');
    }


    public function testApiKeyCallback_KeySaved_SaysAKeyIsSaved(): void {
        $this->saveApiKey(self::SAVED_API_KEY);

        $html = self::renderApiKeyField();

        $this->assertStringContainsString('leave blank to keep the current key', $html, 'The admin should be told a key is saved and how to keep it');
    }


    public function testApiKeyCallback_KeySaved_FieldIsNotRequired(): void {
        $this->saveApiKey(self::SAVED_API_KEY);

        $input = self::apiKeyInputTag(self::renderApiKeyField());

        $this->assertDoesNotMatchRegularExpression('/\srequired\b/', $input, 'With a key saved, the form should submit with the field left blank');
    }


    public function testApiKeyCallback_NoKeySaved_FieldIsRequired(): void {
        $input = self::apiKeyInputTag(self::renderApiKeyField());

        $this->assertMatchesRegularExpression('/\srequired\b/', $input, 'With no key saved, an API key must be entered');
    }


    public function testApiKeyCallback_AnyState_FieldIsNotAutofilled(): void {
        // A password manager would otherwise fill the site's login password into this field and save it as the key
        $input = self::apiKeyInputTag(self::renderApiKeyField());

        $this->assertStringContainsString('autocomplete="new-password"', $input, 'Browsers should not autofill a password into the API key field');
    }


    public function testEncryptApiKey_BlankField_KeepsSavedKey(): void {
        $saved_encrypted_key = \Respectify\respectify_encrypt(self::SAVED_API_KEY);
        $_POST = ['respectify_settings_nonce' => 'valid-test-nonce', 'respectify_api_key' => ''];

        $result = respectify_encrypt_api_key(null, $saved_encrypted_key);

        $this->assertSame($saved_encrypted_key, $result, 'Saving with the field blank should keep the saved key');
    }


    public function testEncryptApiKey_NewKey_ReplacesSavedKey(): void {
        $saved_encrypted_key = \Respectify\respectify_encrypt(self::SAVED_API_KEY);
        $_POST = ['respectify_settings_nonce' => 'valid-test-nonce', 'respectify_api_key' => self::NEW_API_KEY];

        $result = respectify_encrypt_api_key(null, $saved_encrypted_key);

        $this->assertIsString($result);
        $this->assertNotSame(self::NEW_API_KEY, $result, 'The new key should be stored encrypted, not as plain text');
        $this->assertSame(self::NEW_API_KEY, \Respectify\respectify_decrypt($result), 'Saving a new key should replace the saved key');
    }


    public function testTestCredentialsConnection_BlankField_UsesSavedKey(): void {
        $this->saveApiKey(self::SAVED_API_KEY);
        $_POST = ['api_key' => ''];

        $this->assertSame(self::SAVED_API_KEY, respectify_test_credentials_connection()['api_key'], 'Testing with the field blank should test the saved key');
    }


    public function testTestCredentialsConnection_FieldNotPosted_UsesSavedKey(): void {
        $this->saveApiKey(self::SAVED_API_KEY);

        $this->assertSame(self::SAVED_API_KEY, respectify_test_credentials_connection()['api_key'], 'Testing without a posted key should test the saved key');
    }


    public function testTestCredentialsConnection_NewKeyEntered_UsesEnteredKey(): void {
        $this->saveApiKey(self::SAVED_API_KEY);
        $_POST = ['api_key' => self::NEW_API_KEY];

        $this->assertSame(self::NEW_API_KEY, respectify_test_credentials_connection()['api_key'], 'Testing a newly entered key should test that key, before it is saved');
    }


    public function testTestCredentialsConnection_SavedKeyWithPostedServer_UsesSavedServer(): void {
        // The saved key is hidden from the page; posting another server must not be a way to send it there
        $this->saveApiKey(self::SAVED_API_KEY);
        $this->saveServer();
        $_POST = ['api_key' => '', 'base_url' => self::OTHER_BASE_URL, 'api_version' => self::OTHER_API_VERSION];

        $connection = respectify_test_credentials_connection();

        $this->assertSame(self::SAVED_API_KEY, $connection['api_key'], 'The saved key should be tested');
        $this->assertSame(self::SAVED_BASE_URL, $connection['base_url'], 'The saved key should only be sent to the saved server');
        $this->assertSame(self::SAVED_API_VERSION, $connection['api_version'], 'The saved key should only be sent with the saved API version');
    }


    public function testTestCredentialsConnection_KeyNotPostedWithPostedServer_UsesSavedServer(): void {
        $this->saveApiKey(self::SAVED_API_KEY);
        $this->saveServer();
        $_POST = ['base_url' => self::OTHER_BASE_URL, 'api_version' => self::OTHER_API_VERSION];

        $connection = respectify_test_credentials_connection();

        $this->assertSame(self::SAVED_BASE_URL, $connection['base_url'], 'The saved key should only be sent to the saved server');
        $this->assertSame(self::SAVED_API_VERSION, $connection['api_version'], 'The saved key should only be sent with the saved API version');
    }


    public function testTestCredentialsConnection_EnteredKeyWithPostedServer_UsesPostedServer(): void {
        // The admin supplied this key, so testing it against a server they have typed but not saved is fine
        $this->saveApiKey(self::SAVED_API_KEY);
        $this->saveServer();
        $_POST = ['api_key' => self::NEW_API_KEY, 'base_url' => self::OTHER_BASE_URL, 'api_version' => self::OTHER_API_VERSION];

        $connection = respectify_test_credentials_connection();

        $this->assertSame(self::NEW_API_KEY, $connection['api_key'], 'The entered key should be tested');
        $this->assertSame(self::OTHER_BASE_URL, $connection['base_url'], 'An entered key should be tested against the entered server');
        $this->assertSame(self::OTHER_API_VERSION, $connection['api_version'], 'An entered key should be tested with the entered API version');
    }


    public function testTestCredentialsConnection_EnteredKeyNoPostedServer_UsesSavedServer(): void {
        $this->saveServer();
        $_POST = ['api_key' => self::NEW_API_KEY];

        $connection = respectify_test_credentials_connection();

        $this->assertSame(self::SAVED_BASE_URL, $connection['base_url'], 'With no server posted, the saved server should be used');
        $this->assertSame(self::SAVED_API_VERSION, $connection['api_version'], 'With no API version posted, the saved one should be used');
    }


    private function saveServer(): void {
        WordPressStubState::$options[\Respectify\OPTION_BASE_URL] = self::SAVED_BASE_URL;
        WordPressStubState::$options[\Respectify\OPTION_API_VERSION] = self::SAVED_API_VERSION;
    }


    private function saveApiKey(string $api_key): void {
        WordPressStubState::$options[\Respectify\OPTION_API_KEY_ENCRYPTED] = \Respectify\respectify_encrypt($api_key);
    }


    private static function renderApiKeyField(): string {
        ob_start();
        try {
            respectify_api_key_callback();
        } finally {
            $html = (string) ob_get_clean();
        }
        return $html;
    }


    private static function apiKeyInputTag(string $html): string {
        $is_found = preg_match('/<input[^>]*name="respectify_api_key"[^>]*>/', $html, $matches) === 1;
        self::assertTrue($is_found, 'The page should have an API key field');
        return $matches[0];
    }
}
