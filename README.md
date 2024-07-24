
# FS PBX

## Overview

This project started as a fork of the FusionPBX system but has been extensively redesigned. The front end has been redeveloped using Laravel for the backend and Vue.js for the front end. This new implementation aims to enhance user experience, improve performance, and provide a more modern and maintainable codebase.

## Features

- **Laravel Backend**: Robust and scalable backend infrastructure.
- **Vue.js Front End**: Responsive and interactive user interface.
- **Integration with FusionPBX**: Seamless integration with FusionPBX features.
- **Tailwind CSS**: Modern and utility-first CSS framework for styling.
- **Modular Design**: Easy to extend and maintain.

## Screenshots
<img width="2365" alt="image" src="https://github.com/user-attachments/assets/66921621-ab47-4457-ab11-14888c6419ae">

<img width="2409" alt="image" src="https://github.com/user-attachments/assets/6bcd653e-da7a-4de0-9ab6-18a5de02f8c8">

<img width="2401" alt="image" src="https://github.com/user-attachments/assets/18159468-9d74-42ec-b2db-e7cc35bf0162">

<img width="2390" alt="image" src="https://github.com/user-attachments/assets/c5f1265a-147b-4dfe-a85b-bf4541c46ead">

<img width="1600" alt="image" src="https://github.com/user-attachments/assets/89f22edc-ccad-4002-a978-f0fae63f9186">

<img width="1600" alt="image" src="https://github.com/user-attachments/assets/fe3a5405-9f4b-4452-ac12-223cf4c92831">

<img width="2392" alt="image" src="https://github.com/user-attachments/assets/2778637b-e5aa-4174-8e8c-0c637847e45e">

<img width="2409" alt="image" src="https://github.com/user-attachments/assets/4bdc239b-4e15-4099-8304-1a179423296d">

<img width="2417" alt="image" src="https://github.com/user-attachments/assets/5c885878-053c-4e4d-800f-7ad4d919894d">


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


