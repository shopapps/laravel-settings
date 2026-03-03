# Changelog

All notable changes to `laravel-settings` will be documented in this file

## 1.1.0 - 2026-03-02

### Changed
- Renamed `add_setting()` → `setting_add()`
- Renamed `clear_settings()` → `setting_clear_cache()`
- Renamed `delete_setting()` → `setting_delete()`
- Old function names kept as deprecated aliases

### Added
- `setting()` now falls back to `config($key)` when called with a single argument and the database value is `null`

## 1.0.0 - 2024-12-30

- initial release
