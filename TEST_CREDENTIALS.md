# Test credentials

## URLs
- Frontend: http://localhost:5173
- Backend API: http://127.0.0.1:8000/api

## Logins
| Role | Email | Password |
|---|---|---|
| Admin | admin@gmail.com | password |
| Customer | customer@gmail.com | password |

Admin portal: http://localhost:5173/admin
Customer portal: http://localhost:5173/products

## Run it
```
# backend (needs MySQL running, db `testbackend`)
cd testbackend && php artisan migrate:fresh --seed && php artisan serve --port=8000

# frontend
cd testfrontend && npm run dev
```

`migrate:fresh --seed` resets the db and reprints these same credentials, plus demo
stores/products/inventory/discounts (see `testbackend/database/seeders/DatabaseSeeder.php`).

You can also register a new customer account from the UI (Admin has no self-registration —
seeded only, see `ASSUMPTIONS.md` #4).
