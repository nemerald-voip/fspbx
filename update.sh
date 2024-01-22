#!/bin/bash

# Function to print success message
print_success() {
    echo -e "\e[32m$1 \e[0m"  # Green text
}

# Function to print error message
print_error() {
    echo -e "\e[31m$1 \e[0m"  # Red text
}

# Run Composer Install
composer install
if [ $? -eq 0 ]; then
    print_success "Composer install completed successfully."
else
    print_error "Error occurred during 'composer install'."
    exit 1
fi

# Run Composer Dump-Autoload
composer dump-autoload
if [ $? -eq 0 ]; then
    print_success "Composer dump-autoload completed successfully."
else
    print_error "Error occurred during 'composer dump-autoload'."
    exit 1
fi

# Run NPM Install
npm install
if [ $? -eq 0 ]; then
    print_success "NPM install completed successfully."
else
    print_error "Error occurred during 'npm install'."
    exit 1
fi

# Run NPM Run Build
npm run build
if [ $? -eq 0 ]; then
    print_success "NPM run build completed successfully."
else
    print_error "Error occurred during 'npm run build'."
    exit 1
fi

echo "All build tasks completed successfully!"
