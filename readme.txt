=== Zeko Learn ===
Contributors: zeko
Tags: lms, elearning, courses, quizzes, certificates
Requires at least: 5.8
Tested up to: 7.1.2
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A Learning Management System for WordPress. Create courses, enroll students, track progress, issue certificates, and build an online academy.

== Description ==

Zeko Learn is an LMS plugin for WordPress. It provides the tools to create, manage, and sell online courses.

**Course Management**
* Create unlimited courses with sections and lessons
* Support for text, video, quiz, and assignment lesson types
* Drag-and-drop curriculum builder
* Course categories and skill tags
* Course pricing (free and paid)
* Instructor roles and dashboards

**Learning Experience**
* Student dashboard with enrolled courses, certificates, and learning streak
* Progress tracking with percentage completion
* Timestamped notes on video lessons
* Lesson bookmarks
* Course wishlist
* Learning goals with target dates

**Quizzes & Assignments**
* Multiple choice, true/false, fill-in-the-blank questions
* Quiz timer with auto-submit
* Pass/fail scoring with configurable passing grade
* Assignment submissions with file upload
* Instructor grading with feedback

**Certificates**
* Auto-generated certificates on course completion
* Unique certificate numbers for verification
* Print-ready certificate template
* Public verification endpoint

**Social & Engagement**
* Course reviews with ratings
* Instructor replies to reviews
* Course discussions with threading
* Upvote/downvote on discussions
* Best answer marking by instructors
* Course announcements

**REST API**
* Full REST API at `/wp-json/zeko-learn/v1/`
* Courses, sections, lessons CRUD
* Enrollments, progress, quizzes
* Instructor and user endpoints
* Certificate verification

== Installation ==

1. Upload the `zeko-learn` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu
3. Go to Learn > Settings to configure the plugin
4. Create your first course under Learn > Courses
5. Use the `[zeko_learn_catalog]` shortcode to display the course catalog

== Frequently Asked Questions ==

= Does this plugin work with WooCommerce? =

Zeko Learn is designed as a standalone LMS. It integrates with the Zeko ecosystem of plugins but works independently.

= Can I use this without Zeko Pay? =

Yes. Free courses can be created without any payment plugin. For paid courses, the plugin builds a checkout redirect URL that can integrate with any payment system.

= How do students access courses? =

Students enroll via the course page, then access lessons through the course player at `/courses/{course-slug}/learn/`.

= Can I issue certificates? =

Yes. Certificates are automatically issued when a student completes all lessons in a course. Certificates have unique numbers that can be verified publicly.

= Is there a REST API? =

Yes. A full REST API is available at `/wp-json/zeko-learn/v1/`. See the API documentation in the plugin files.

== External Services ==

Zeko Learn can embed third-party video players when a course author adds an
external video URL to a lesson. Everything is optional and off by default.

* **YouTube (no-cookie mode)** — When a lesson uses a YouTube URL and embeds are
  enabled, the video is loaded from `www.youtube-nocookie.com/embed/{id}` by
  default (so YouTube receives no cookies from your visitors). What is sent: the
  embed request itself; YouTube's own tracking/privacy policies apply. When:
  only when an author adds a YouTube URL and embedding is enabled.
* **Vimeo** — When a lesson uses a Vimeo URL and embeds are enabled, the video
  loads from `player.vimeo.com/video/{id}`. What is sent: the embed request
  itself. When: only when an author adds a Vimeo URL and embedding is enabled.

Embedding can be turned off entirely (admin setting `Disable video embeds`), in
which case external video URLs never load third-party players; only https URLs
are rendered and a hard allow-list of video hosts is enforced. Endpoint and
data-field detail (with file:line citations) is in `REMOTE-SERVICES.txt` in
this plugin folder.

== Third-party resources ==

Zeko Learn bundles one third-party JavaScript asset: the **html2pdf.js bundle**
(MIT) at `assets/vendor/html2pdf/html2pdf.bundle.min.js`, used for certificate
PDF export (`templates/certificate.php`). The bundle also contains html2canvas
and jsPDF (both MIT). Copyright (c) 2021 Erik Koopmans, (c) 2018 Dynamic
Artists LLC, (c) 2016 Niklas von Hertzen, (c) 2010 James Hall. Full license
text is in `THIRD-PARTY-NOTICES.txt` in this plugin folder (the bundle's own
`html2pdf.bundle.min.js.LICENSE.txt` ships alongside it). MIT is
GPL-2.0-or-later-compatible. All other assets are original Zeko work; Zeko
Learn makes no server-side outbound requests.

== Dependencies ==

Zeko Learn works standalone. It integrates with the Zeko ecosystem (dashboard tabs, activity feed, Zeko Pay for paid courses) but none of those plugins are required.

== Privacy ==

Data stored includes student enrollments, lesson progress, quiz attempts, assignment submissions (including uploaded files), certificates, reviews, discussions, and course bookmarks. Video lessons may load from YouTube/Vimeo as described in External Services, which can send your IP to those providers.

== Uninstall ==

Deleting the plugin drops all Zeko Learn tables (courses, sections, lessons, quizzes, attempts, assignments, certificates, and related data) and removes its options. Course content you created outside the plugin is not affected.

== Troubleshooting ==

* Course not appearing? Confirm the course is published and the `[zeko_learn_catalog]` shortcode is on the page.
* Certificate not issued? A certificate is generated only when all required lessons are marked complete.
* Video won't play? Check the lesson URL is in the allowed video hosts; embedding can be disabled under Learn > Settings.

== Screenshots ==

1. Learning dashboard with course progress

== Changelog ==

= 1.0.0 =
* Initial release
* 26 database tables
* Course, section, lesson management
* Quiz engine with multiple question types
* Assignment submissions and grading
* Certificate generation and verification
* Student and instructor dashboards
* Course reviews and discussions
* REST API with 26 endpoints
* 37 AJAX handlers
* Object cache support
* Input sanitization and output escaping
