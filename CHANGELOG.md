# Changelog

## 2026-09-15

- Report the effective model on every result: ACP prompt answers name it in `_meta.modelId`, and headless runs fall back to the model on the init and assistant messages, so default-model runs stay attributable.

## 2026-09-08

- Fix new read-only headless runs on macOS with Grok 1.0.13 and Docker Desktop's standard socket symlink. Install an SDK-owned custom profile that inherits read-only filesystem grants and hook protection while denying runtime and Docker directories through Seatbelt globs. Other versions, platforms, sandbox modes, and nonstandard socket layouts keep the native profile. Existing user profiles are preserved; configuration failures remain errors.

## 1.0.0 - 2026-09-04

- Initial release: ACP sessions over `grok agent stdio`, headless `grok -p`, and `_x.ai/billing`.
