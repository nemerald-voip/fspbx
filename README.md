
# Project Name

## Overview

This project started as a fork of the FusionPBX system but has been extensively redesigned. The front end has been redeveloped using Laravel for the backend and Vue.js for the front end. This new implementation aims to enhance user experience, improve performance, and provide a more modern and maintainable codebase.

## Features

- **Laravel Backend**: Robust and scalable backend infrastructure.
- **Vue.js Front End**: Responsive and interactive user interface.
- **Integration with FusionPBX**: Seamless integration with FusionPBX features.
- **Tailwind CSS**: Modern and utility-first CSS framework for styling.
- **Modular Design**: Easy to extend and maintain.

## Prerequisites

Before you begin, ensure you have met the following requirements:

- PHP 8.0 or higher
- Composer
- Node.js and npm
- MySQL or PostgreSQL
- FusionPBX installed

## Installation

### Backend

1. **Clone the Repository**

   ```bash
   git clone https://github.com/nemerald-voip/laravel-freeswitch.git

2. **Clone FusionPBX repository into public folder**

   ```bash
   cd public
   git clone https://github.com/fusionpbx/fusionpbx.git
   
3. **Replace index.php file install folder**

4. **Environment Configuration**
   Copy the `.env.example` to `.env` and configure your database and other environment settings.

   ```bash
    cp .env.example .env
    php artisan key:generate

5. **Run Migrations**

   ```bash
    php artisan migrate

5. **Run the update command**

   ```bash
    php artisan app:update

### Usage
After completing the installation steps, you can access the application at your domain.

### Contact
For any questions or feedback, please contact us for support.


