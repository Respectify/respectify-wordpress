Todo:
x publish new version of async clinet (renamed logical fallacy 'explanation' field)
x handle escaping in the comments - gets to PHP class double-escaped?
x sanitise the AI's text before emitting it? (In PHP class?)
- avoid quoting the same phrase twice (in Python layer?)


- Give opportunity to post anyway:
  - when x score or higher
  - build reputation score into Respectify - per site, per user, so not shared 
  - (but allow for federation: opt-in, but by email someone can be recognised and build a score across sites. Have to authenticate with
    Respectify that way though.)
  - History: a first version (commit 135e95b) let any commenter override a revision request; its server side was removed in
    dfc3d26 (Dec 2024) and the leftover front-end button handler was deleted in 2026-10. If it comes back, make it an admin
    opt-in, gated on the reputation score, and never for deletions, spam, suspected spam or the floored health items.


- API keys, rework DB, Alembic upgrade
- Add organisation to DB?
(- add support for sites, so can build a reputation per site)
- Frontend!

