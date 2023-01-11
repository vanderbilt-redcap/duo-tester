# DUO tester

REDCAp plugin to help identify issues related to the DUO Two-Factor Authentication (2FA) integration.

## Installation

Download this repo, unzip it, and copy it in the REDCap plugins folder.

## Usage

Download the latest release, unzip the content, and copy it in the REDCap plugins folder.
[example](https://raw.githubusercontent.com/vanderbilt-redcap/duo-tester/main/assets/example_1.png)

### Expected steps in DUO 2FA:

* user logs in providing REDCap credentials
* "two steps verification" page is displayed
* a Duo session is created: `Duo::makeSession($currentUser, $currentPage)`
    * a Duo store is created and saved in the `redcap_sessions` table: it stores a state (session ID), the REDCap username, and the redirect URL
* user selects the "DUO" option from the list
    * the javascript function `selectTFStep1` is triggered with 2 parameters: `'duo', {state: DUO_SESSION_ID}`
    * the user is redirected to `/twoFA/index.php`
* a Duo facade is created
* the Duo facade launches the universalPrompt
    * the Duo Store is recreated from the session table using the state (DUO session ID)
    * Duo performs an Health check
    * user is redirected to DUO
* the user is redirected to REDCap
