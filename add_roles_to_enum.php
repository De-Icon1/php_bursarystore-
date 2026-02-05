<?php
// Helper disabled: role migration not required in stationery-only mode.
// Previously this script modified the `users.role` enum to add 'vc'/'transport'.
// To avoid accidental schema changes, this file is now a safe no-op.

echo "This helper is disabled. No role migration performed.\n";
exit;
?>
