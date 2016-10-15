Originally developed and designed by Nuclear: https://github.com/ncla/Portal-2-Leaderboard


Server requirements:

1. Basic LAMP/XAMPP webserver. Note that for SQL, MariaDB is known to cause issues when using query variables.
2. Google account.


Server configuration:

1. Set the 'public' folder as the server's document root.
2. For Apache, enable 'mod_rewrite' and 'mod_expires' for image caching and beautiful URLs.
3. For PHP, enable the cURL extension for scraping the Steam leaderboards.
4. Import database dump data/leaderboard.sql into phpMyAdmin.
5. Configure database authorization settings in secret/database.json.
6. Set database timezone to UTC by executing data/setDatabaseTimeZoneUTC.php


Configuring Google Drive for storing demos

5. Go to 
https://console.developers.google.com
6. Create a project and activate the Google Drive API 
7. Create an OAuth client ID. Download and copy the client secret file associated with this ID, and paste it in secret/client_secret.json. 
8. Execute 'php util/authorizeGoogleDrive.php' from the command line to provide the project access to Google Drive.
9. Configure the demos folder in classes/demoManager.php.


Fetching data:

1. Updating scores and refreshing the server cache accordingly is performed by running data/fetchNewScores.php. 
2. Updating user data is done by running data/fetchNewProfileData.php. This requires a Steam API developer key which has 
to be placed in secret/steam_api_key.json. For obtaining an API key, go to 
https://steamcommunity.com/dev


Software licensed under CC Attribution - Non-commercial license.
https://creativecommons.org/licenses/by-nc/4.0/legalcode
