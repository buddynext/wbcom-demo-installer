# BuddyPress / BuddyBoss provider (1.1.0)

## Problem

Theme demos and customer sites run BuddyPress or BuddyBoss Platform, and the installer builds nothing for either.
The feed is whatever other products' bridges post while their content is built, so on A1 (Reign + BuddyPress) the
first 20 items are all "X is attending Y", every one stamped with the build time. There are no member updates, no
media posts, no group activity, and members have no history of their own.

## Root cause

- No provider owns BuddyPress content (updates, groups, friendships, media in activity).
- BuddyNext's provider dates items other products create (`buddynext_post_before_save` + `Clock`), and nothing does
  the same for `bp_activity`, so every bridged item lands at the build time.
- Repetitive bridged items (one per RSVP, enrolment or listing) are not thinned, so one product floods the feed.

## Design

One provider, `buddypress`, covering BuddyPress and BuddyBoss Platform (same `bp_*` API; BuddyBoss differences are
guarded where they exist). Content lives in `content/buddypress.php`, cherry-picked from BuddyNext's sample posts so
the voice matches. Product APIs only: `bp_activity_add`, `bp_activity_new_comment`, `bp_activity_add_user_favorite`,
`groups_create_group`, `groups_join_group`, `groups_post_update`, `friends_add_friend` + `friends_accept_friendship`,
`xprofile_set_field_data`, `bp_core_avatar_*` / BuddyBoss equivalents.

Steps (each well inside one request):

1. `profiles`: xprofile name and bio fields, BP avatars and cover images from the cast photos.
2. `groups`: 8 groups (public, private, hidden), avatar and cover, a creator and 4 to 10 members each.
3. `friends`: a believable friendship graph (each person 4 to 8 friends).
4. `updates:N`: mixed activity, dated across the timeline: status updates, link shares, updates with photos (through
   MediaVerse when it is active, otherwise BP attachments), group posts, @mentions, comments and favourites.
   Every cast member gets several items of their own.
5. `extras`: profile-photo changes, "joined group", "became friends" items, spread over the timeline.

Bridged items:

- `boot()` hooks `bp_activity_before_save`: while a build runs, an item with no date gets `Clock::now()`, so items
  posted by other products' bridges (Eventonomy RSVPs, Learnomy enrolments, Listora listings) carry the date of the
  thing that caused them; each is recorded in the ledger.
- Thin repetitive bridged types: per type, keep at most a few per day in the site-wide stream (`hide_sitewide`), so
  one product cannot flood the feed. The items stay on member profiles.

Removal: ledger rows newest first through `bp_activity_delete`, `groups_delete_group`, `friends_remove_friend`;
uploaded images through `delete_post`.

Guide: the provider's own guide (member: post an update, join a group, add a friend, see notifications; owner:
components, groups, moderation), replacing the guide-only BuddyPress / BuddyBoss integrations.

## Existing demo sites

The engine refuses a second build. Add `wp wbcom-demo build --add=<ids>`: appends steps for providers not built yet
to the current demo and ledger, so Finish still removes everything. Used to add BuddyPress content to A1, A2 and X1,
and for Product Roadmap and others later. One-off clean-up on those sites: re-date and thin the bridged items already
in their feeds.

## Verification

- Lab matrix rows: BuddyPress fresh, BuddyBoss fresh, BuddyPress + MediaVerse + Eventonomy + Learnomy (bridges),
  `--add` on an existing demo, Finish removes every row.
- Browser: feed shows at least 6 activity types in its first 20 items, no type more than 4 times; every cast member's
  profile shows their own activity; groups have members and posts; 1440 and 390.
- phpcs, PHPStan level 6.
