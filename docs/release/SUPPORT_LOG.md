# Release support log

Record production issues here. Do not put passwords, bank details, or customer identity numbers in this file if it will be committed.

| Date | Issue | User | Module | Severity | Workaround | Resolution | Version fixed |
| --- | --- | --- | --- | --- | --- | --- | --- |
| 2026-10-02 | Fresh install stopped because `seed.sql` had a sentence that was not a SQL comment | Installer | Database | Blocker | Use migrations `001`–`025` on an empty database | The sentence is now a comment. `schema.sql` then `seed.sql` completed on an empty database | 1.1.0 |
| 2026-10-02 | Two log rotations in the same second could skip the second file | Administrator | System health | Minor | Wait a second and run cron again | The archive name gains a short suffix when the timestamp is already used | 1.1.0 |
