# Alloy system recordings

Use this directory as `sound_prefix`; prompt paths are relative to it. Callback
prompts live in `call_center/callbacks`, and spoken digits in `digits`.

Callback prompts and digits are mono, 16-bit PCM WAV at 16 kHz. FreeSWITCH
resamples them for 8 kHz calls and other negotiated rates. A single set avoids
duplicating recordings in rate-specific directories. Existing recordings for
other features retain their original formats.

Preserve the previous channel `sound_prefix` and `sound_prefix_enforced` values
when temporarily selecting a voice. Native `say` requires
`sound_prefix_enforced=true` while playing Alloy
digits, followed by restoration of both values.

Callback generation uses the configured FS PBX OpenAI service:

```sh
php Modules/ContactCenter/Resources/sounds/generate.php
```

This generates missing files using `gpt-4o-mini-tts-2025-12-15`, voice `alloy`,
speed `1.05`, then converts them with FFmpeg. `--force` replaces existing files.
Prompt text is maintained in the module's `Resources/sounds/prompts.json`.
To regenerate just the callback offer while preserving the other recordings:

```sh
php Modules/ContactCenter/Resources/sounds/generate.php --only=call_center/callbacks/offer --force
```

The offer explains that callers keep their place in line. The queue plays its
configured callback key immediately after this recording.

The existing digit `1.wav` was preserved and converted from 24 kHz to 16 kHz,
with the incomplete WAV header repaired and trailing silence shortened. Existing
callback prompts and digits were sped up to 1.05 times their original speed
without changing pitch; the offer was regenerated with the expanded wording.
