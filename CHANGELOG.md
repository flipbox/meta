Changelog
=========
## 1.0.0-beta.8 - 2017-09-29
### Changed
- Meta elements now have status and conform to enabled/disabled query.

## 1.0.0-beta.7 - 2017-09-25
### Changed
- Registering twig variables using latest Craft event.
- Explicitly calling `->all()` on Query objects

## 1.0.0-beta.6 - 2017-07-20
### Added
- Content migration for `1.0.0-beta.5` release

## 1.0.0-beta.5 - 2017-07-19
### Changed
- Content tables use the field Id instead of the field handle to add flexibility and eliminate duplicates.

## 1.0.0-beta.4 - 2017-07-13
### Fixed
- Sort order not persisting after field save [#1](https://github.com/flipbox/meta/issues/1)
- the `siteId` property was not getting set properly when deleting an element.

## 1.0.0-beta.3 - 2017-06-12
### Added
- Introduced field setting to indicate input template override.

### Fixed
- When nested in a matrix block, the field prefix would be incorrect
- When nested in a matrix block and the field type changed, the settings would not be saved correctly. 

## 1.0.0-beta.2 - 2017-06-11
### Changed
- General code styling and return types for various methods

## 1.0.0-beta.1 - 2017-05-17
### Changed
- Changed base plugin class to 'Meta' in favor of semantic usage

## [1.0.0-beta] - 2017-03-29
Initial release.
