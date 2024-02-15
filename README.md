# Clocking
###   [Version] 1.0.0
###   [Author] Lucas FANECH

- [Description](#description)
- [Installation](#installation)
- [Usage](#usage)

## Description
This Symfony project is a simple clocking system. It allows users to clock in and out, and to view their clocking history. It automatically calculates the time worked and the total time worked in a day. It also allows users to view their time worked in a week.
The user can also view when he is allowed to clock out in order to work the required amount of hours in a day.

## Installation
1. Clone the repository
2. Install the dependencies
```bash
composer install
```
3. Create the database
import the `clocking.sql` file into your mysql/mariadb database
4. Configure the database
For example in the `.env` file : 
a user "symfony" with no password and a database "clocking"
```bash
# .env
DATABASE_URL="mysql://symfony:@127.0.0.1:3306/clocking?serverVersion=8&charset=utf8mb4"
```

5. Start the server
```bash
symfony server:start
```

6. Open your browser and go to `http://127.0.0.1:8000` to access the application


## Usage

1. Log in with the following credentials:
Username: `test@gmail.com`
Password: `test`

This account is an admin account, it allows you to create new users and to manage their accounts.

2. Click on "test" (name of the user) in the top right corner to access the user's profile.
3. Change the email and password if needed.
4. Click on "Admin Panel" to access the admin panel. And create new users if needed.
5. Log out and log in with the new user's credentials to test the clocking system.
6. You should be able to clock in and out and to view your clocking history by navigating with the arrows.
7. You can also view your time worked in a week and when you are allowed to clock out in order to work the required amount of hours in a day.

