#!/bin/sh

# Function to print success message
print_success() {
    echo "\033[32m$1 \033[0m"  # Green text
}

# Function to print error message
print_error() {
    echo "\033[31m$1 \033[0m"  # Red text
}

# Check if cron is installed, if not, install it
if ! command -v crontab >/dev/null 2>&1; then
    echo "Cron is not installed. Installing..."
    apt update && apt install -y cron
    systemctl enable cron
    systemctl start cron
fi

# Define the cron job entries as a string
CRON_JOBS="
* * * * * cd /var/www/fspbx; /usr/bin/php /var/www/fspbx/public/app/xml_cdr/xml_cdr_import.php 100 abcdef >/dev/null 2>&1
* * * * * cd /var/www/fspbx; /usr/bin/php /var/www/fspbx/public/app/xml_cdr/xml_cdr_import.php 100 01234 >/dev/null 2>&1
* * * * * cd /var/www/fspbx; /usr/bin/php /var/www/fspbx/public/app/xml_cdr/xml_cdr_import.php 100 56789 >/dev/null 2>&1
* * * * * cd /var/www/fspbx && php artisan schedule:run >> /dev/null 2>&1
"

# Define the cron job entries to remove (regex pattern matching)
REMOVE_CRON_JOBS="
^\* \* \* \* \* /usr/bin/php /var/www/fusionpbx/app/xml_cdr/xml_cdr_import.php 300$
"

# Backup the existing crontab
CRON_FILE=$(mktemp)
crontab -l > "$CRON_FILE" 2>/dev/null || true

# Remove unwanted cron jobs (line-by-line)
echo "$REMOVE_CRON_JOBS" | while IFS= read -r pattern; do
    if [ -n "$pattern" ]; then
        sed -i "\|$pattern|d" "$CRON_FILE"
    fi
done

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
