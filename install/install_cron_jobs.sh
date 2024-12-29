#!/bin/sh

# Function to print success message
print_success() {
    echo "\033[32m$1 \033[0m"  # Green text
}

# Function to print error message
print_error() {
    echo "\033[31m$1 \033[0m"  # Red text
}

# Define the cron job entries as a string
CRON_JOBS="
* * * * * cd /var/www/fspbx; /usr/bin/php /var/www/fspbx/public/app/xml_cdr/xml_cdr_import.php 100 abcdef >/dev/null 2>&1
* * * * * cd /var/www/fspbx; /usr/bin/php /var/www/fspbx/public/app/xml_cdr/xml_cdr_import.php 100 01234 >/dev/null 2>&1
* * * * * cd /var/www/fspbx; /usr/bin/php /var/www/fspbx/public/app/xml_cdr/xml_cdr_import.php 100 56789 >/dev/null 2>&1
* * * * * cd /var/www/fspbx && php artisan schedule:run >> /dev/null 2>&1
"

# Backup the existing crontab
CRON_FILE=$(mktemp)
crontab -l > "$CRON_FILE" 2>/dev/null || true

# Loop through each cron job and add it if it doesn't exist
echo "$CRON_JOBS" | while read -r job; do
    if [ -n "$job" ] && ! grep -Fxq "$job" "$CRON_FILE"; then
        echo "$job" >> "$CRON_FILE"
        echo "Added cron job: $job"
    else
        echo "Cron job already exists: $job"
    fi
done

# Apply the updated crontab
crontab "$CRON_FILE" && rm "$CRON_FILE"
if [ $? -eq 0 ]; then
    print_success "Cron jobs updated successfully."
else
    print_error "Failed to update cron jobs."
    exit 1
fi
