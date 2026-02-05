<?php
// This helper script previously added a `quantity` column to the optional
// `tyre_assignment` table. The project may no longer include that table.
// To avoid accidental runtime errors, this script now checks for the
// presence of the table before attempting to alter it.

// Obsolete helper: tyre/vehicle features were removed for the stationery-only app.
// Keeping this file as a no-op to avoid accidental execution of DB changes.

echo "This helper is obsolete in stationery-only mode. No action taken.\n";
exit;
?>
