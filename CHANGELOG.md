# Luma | Routing Component Changelog

## [1.6.2] - 2024-07-15
### Added
- N/A

### Changed
- N/A

### Deprecated
- N/A

### Removed
- N/A

### Fixed
- Fixed critical issue with protected routes; `processRequireRolesAttribute` and `processRequirePermissionsAttribute`

### Security
- N/A

---

## [1.6.1] - 2024-05-05
### Added
- Using `AbstractRouteProtectionAttribute`

### Changed
- N/A

### Deprecated
- N/A

### Removed
- N/A

### Fixed
- Fixed issue where route protection attributes were not respecting `redirectPath` and `message` arguments.

### Security
- N/A

---

## [1.6.0] - 2024-05-05
### Added
- Added `redirectPath` and `message` to route protection attributes

### Changed
- N/A

### Deprecated
- N/A

### Removed
- N/A

### Fixed
- N/A

### Security
- N/A

---

## [1.5.2] - 2024-05-02
### Added
- N/A

### Changed
- N/A

### Deprecated
- N/A

### Removed
- N/A

### Fixed
- Bug fix for `RequireUnauthenticated` attribute not being handled correctly

### Security
- N/A

---

## [1.5.1] - 2024-05-02
### Added
- Added additional attribute `RequireUnauthenticated`

### Changed
- N/A

### Deprecated
- N/A

### Removed
- N/A

### Fixed
- N/A

### Security
- N/A

---

## [1.5.0] - 2024-05-02
### Added
- Added route protection attributes: `RequireAuthentication`, `RequireRoles` and `RequirePermissions`

### Changed
- N/A

### Deprecated
- N/A

### Removed
- N/A

### Fixed
- N/A

### Security
- N/A

---

## [1.4.2] - 2024-04-17
### Added
- N/A

### Changed
- N/A

### Deprecated
- N/A

### Removed
- N/A

### Fixed
- N/A

### Security
- Update `lumax/http-component` dependency from `2.0.6` -> `2.1.0`

---

## [1.4.1] - 2024-04-17
### Added
- N/A

### Changed
- N/A

### Deprecated
- N/A

### Removed
- N/A

### Fixed
- Routes now properly considering defined methods

### Security
- N/A

---

## [1.4.0] - 2024-04-17
### Added
- N/A

### Changed
- Controller methods can now optionally use the current `Request` by typehinting the **first** parameter with `Request`

### Deprecated
- N/A

### Removed
- Removed Jenkins pipeline

### Fixed
- N/A

### Security
- N/A

## [1.3.9] - 2024-03-17
### Added
- N/A

### Changed
- N/A

### Deprecated
- N/A

### Removed
- N/A

### Fixed
- N/A

### Security
- Additional unit tests 

## [1.3.8] - 2024-03-02
### Added
- Added build pipeline
- Added CHANGELOG

### Changed
- N/A

### Deprecated
- N/A

### Removed
- N/A

### Fixed
- N/A

### Security
- Added additional unit tests