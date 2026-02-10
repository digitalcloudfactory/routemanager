# routemanager
TODO
-----

- UI update to have map on left & info on right side in 1 colum
- UI filter - Custom double range slider on distance & elevation

- tumbnail view instead of map or table view?
- ability to have 'NOT' in filters

- Strava Authentication with Token expiration
Strava tokens expire every 6 hours. Even if you fix the login page, you will eventually get an "Unauthorized" error in routes.php. You'll eventually need to add a check to see if token_expires_at has passed and use the refresh_token to get a new one.
