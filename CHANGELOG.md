# Changelog

All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](https://semver.org/) and
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

## [3.1.0] - 2026-06-15

Adds password policy enforcement and user name autocomplete to the select-user form.

### Added
- Enforce MediaWiki password policy (`$wgPasswordPolicy`) when setting a password via EditAccount; policy violations are now rejected with a descriptive error message instead of being silently accepted [`bd24611`](https://github.com/gesinn-it-pub/EditAccount/commit/bd24611)
- User name autocomplete in the select-user form, matching the behaviour of Special:UserRights [`bdc4616`](https://github.com/gesinn-it-pub/EditAccount/commit/bdc4616)

### Fixed
- Escape unquoted double quote in `editaccount-usage-close` message in `de` and `de-formal` translations, which caused JSON parse errors [`7c45c68`](https://github.com/gesinn-it-pub/EditAccount/commit/7c45c68)

## [3.0.1] - 2026-06-15

Updates all development dependencies and bumps the CI matrix to PHP 8.3 for MW 1.43.

### Changed
- Bump `mediawiki/mediawiki-phan-config` from 0.14.0 to 0.20.0 [`60ad0c4`](https://github.com/gesinn-it-pub/EditAccount/commit/60ad0c4)
- Bump `mediawiki/mediawiki-codesniffer` from 43.0.0 to 48.0.0 [`6f0e406`](https://github.com/gesinn-it-pub/EditAccount/commit/6f0e406)
- Update npm dependencies, bump grunt to 1.6.2 to fix minimatch ReDoS vulnerabilities [`7b69f4c`](https://github.com/gesinn-it-pub/EditAccount/commit/7b69f4c)
- Bump CI matrix for MW 1.43 from PHP 8.1 to 8.3 [`2dc41f5`](https://github.com/gesinn-it-pub/EditAccount/commit/2dc41f5)

## [3.0.0] - 2026-06-15

Major rewrite modernising the extension for MediaWiki 1.36+: full namespace migration,
dependency injection, removal of legacy Wikia/ShoutWiki code, and a security fix for
random token generation.

### Breaking Changes
- Migrate all classes to namespace `MediaWiki\Extension\EditAccount` [`5ef93bc`](https://github.com/gesinn-it-pub/EditAccount/commit/5ef93bc)
- Remove ShoutWiki/Wikia legacy code [`e213b0f`](https://github.com/gesinn-it-pub/EditAccount/commit/e213b0f)
- Bump minimum MediaWiki requirement to >= 1.36.0 [`e260032`](https://github.com/gesinn-it-pub/EditAccount/commit/e260032)
- Replace `MediaWikiServices::getInstance()` with dependency injection [`093cede`](https://github.com/gesinn-it-pub/EditAccount/commit/093cede)

### Fixed
- Replace `md5(mt_rand())` with cryptographically secure random [`6034f9d`](https://github.com/gesinn-it-pub/EditAccount/commit/6034f9d)
- Correct three bugs found in code review [`c95730d`](https://github.com/gesinn-it-pub/EditAccount/commit/c95730d)
- Replace `MediaWiki\Request\FauxRequest` with global `\FauxRequest` for MW 1.39 compat [`8209780`](https://github.com/gesinn-it-pub/EditAccount/commit/8209780)
- Replace `assertMatchesRegularExpression` with `preg_match` for PHPUnit 8.5 compat [`16577e8`](https://github.com/gesinn-it-pub/EditAccount/commit/16577e8)

### Changed
- Migrate `SpecialContributionsBeforeMainOutput` to class-based hook handler [`0222d73`](https://github.com/gesinn-it-pub/EditAccount/commit/0222d73)
- `CloseAccount` extends `SpecialPage` directly instead of `EditAccount` [`9f636ac`](https://github.com/gesinn-it-pub/EditAccount/commit/9f636ac)
- Use `UserOptionsManager` instead of deprecated User methods [`e52b3d4`](https://github.com/gesinn-it-pub/EditAccount/commit/e52b3d4)
- Update i18n messages to use sitename [`50cea4d`](https://github.com/gesinn-it-pub/EditAccount/commit/50cea4d)
- Add README with Codecov badge, description, installation and usage [`3e7b510`](https://github.com/gesinn-it-pub/EditAccount/commit/3e7b510)

[Unreleased]: https://github.com/gesinn-it-pub/EditAccount/compare/3.1.0...HEAD
[3.1.0]: https://github.com/gesinn-it-pub/EditAccount/compare/3.0.1...3.1.0
[3.0.1]: https://github.com/gesinn-it-pub/EditAccount/compare/3.0.0...3.0.1
[3.0.0]: https://github.com/gesinn-it-pub/EditAccount/releases/tag/3.0.0
