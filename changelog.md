# Change Log
All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/).

## 2.1.3 - 2021-01-22

### Fixed

- [Issue 4286](https://github.com/firefly-iii/firefly-iii/issues/4286) Issue with date parsing fixed.
- Update libraries

## 2.1.2 - 2021-01-19

### Fixed

- [Issue 4286](https://github.com/firefly-iii/firefly-iii/issues/4286) Missing imports broke the importer. Apologies!

## 2.1.1 - 2021-01-02

### Changed

- Update libraries and documentation links.

## 2.1.0 - 2020-11-29

⚠️ Several changes in this release may break Firefly III's duplication detection or are backwards incompatible.

### Changed

- ⚠️ All environment variables that used to be called "URI" are now called "URL" because I finally learned the difference between a URL and a URI.

## 2.0.5 - 2020-11-25

### Changed

- Commands are now prefixed with `importer:` rather than `ynab:`.

## 2.0.4 - 2020-11-25

### Fixed

- [Issue 4088](https://github.com/firefly-iii/firefly-iii/issues/4088) The YNAB importer would add "(no description)" twice to some transactions.

### Changed

- Minimum version of Firefly III is now 5.4.0

## 2.0.3 - 2020-09-06

### Fixed
- No longer requires the `APP_KEY` variable.

## 2.0.2 - 2020-08-13

### Added
- Reset button in case the import fails.

### Removed
- Removed some debug statements.

### Fixed
- [Issue 3694](https://github.com/firefly-iii/firefly-iii/issues/3694) Null pointer when payee_name is empty.
- [Issue 3698](https://github.com/firefly-iii/firefly-iii/issues/3698) Bad call to logger.

## 2.0.1 - 2020-08-13

### Added
- Reset button in case the import fails.

### Removed
- Removed some debug statements.

### Fixed
- [Issue 3694](https://github.com/firefly-iii/firefly-iii/issues/3694) Null pointer when payee_name is empty.

## 2.0.0 - 2020-07-12

- Now requires PHP 7.4. Make sure you update!
- You can now set a vanity URL. Check out the example environment file.
- This version requires Firefly III v5.3.0

## 1.0.1 - 2020-06-21

- Update packages.
- New configuration for verify TLS and connection timeouts.

## 1.0.0 - 2020-05-05

This release was preceded by several alpha versions:

- 1.0.0-alpha.1 on 2020-04-05
- 1.0.0-alpha.2 on 2020-04-05

### Added
- Initial release.

### Changed
- Initial release.

### Deprecated
- Initial release.

### Removed
- Initial release.

### Fixed
- Initial release.

### Security
- Initial release.
