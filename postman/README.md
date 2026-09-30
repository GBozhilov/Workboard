# WorkBoard Postman

Import these files into Postman to exercise the WorkBoard Stage 13 REST API against a local Docker stack (`http://localhost:8081`).

## Setup

1. Import **`WorkBoard.postman_collection.json`** (Collection).
2. Import **`WorkBoard.example.postman_environment.json`** (Environment).
3. Duplicate the environment in Postman and rename it if you like (for example **WorkBoard Local**).
4. Set **`email`** and **`password`** to a valid WorkBoard user (same credentials as the web app).
5. Select your WorkBoard environment in the Postman environment dropdown.

### Local environment (not in Git)

`WorkBoard.local.postman_environment.json` is **gitignored**. On your machine you can keep it in `postman/` with real credentials. New clones should duplicate `WorkBoard.example.postman_environment.json` to that filename (or import the example and save as **WorkBoard Local**).

Default local template (if you use `php artisan db:seed`):

- `email`: `test@example.com`
- `password`: `password` (Laravel factory default)

If you registered a different user in the web app, replace `email` and `password` with those values. Leave `token` and ID variables empty until Login and create requests fill them.

## Authentication

1. Open **1. Auth → Login - get Sanctum token** and send the request.
2. The test script stores **`token`** (Sanctum Bearer token) and **`user_id`** in the environment.
3. The collection uses **Bearer auth** (`{{token}}`) for all other requests.

**Current user** and **Logout** are under the same folder. Logout clears **`token`** from the environment.

## Automatic IDs

After successful creates, tests save IDs for chained requests:

| Request | Variables set |
|--------|------------------|
| Login | `token`, `user_id` |
| Create project | `project_id` |
| Create task | `task_id` |
| Create comment | `comment_id` |
| Create or reuse tag and attach to task | `tag_id`, `tag_slug` |

List/Get/Update/Delete requests use `{{project_id}}`, `{{task_id}}`, `{{comment_id}}`, and `{{tag_id}}` from the environment. No hardcoded database IDs are required.

## DELETE responses

Destructive requests (**Delete project**, **Delete task**, **Delete comment**, **Detach tag**) usually return **`204 No Content`** with an empty body. Run deletes only after you have finished testing dependent resources (comments and tags before tasks; tasks before projects).

## Recommended manual flow

1. **Login**
2. **Current user**
3. **Create project** → `project_id`
4. **Get project** / **Update project**
5. **Create task** → `task_id`
6. **Get task** / **Update task** / **List tasks** (optional filters in query params)
7. **Create comment** → `comment_id`
8. **List comments** / **Delete comment**
9. **List project tags** / **Create or reuse tag** / **Attach existing tag** / **Detach tag**
10. **Delete task**
11. **Delete project**
12. **Logout**

## Environment variables

| Variable | Purpose |
|----------|---------|
| `base_url` | API root (default `http://localhost:8081`) |
| `email` | Login email |
| `password` | Login password |
| `token` | Sanctum Bearer token (set by Login) |
| `user_id` | Authenticated user ID |
| `project_id` | Last created project |
| `task_id` | Last created task |
| `comment_id` | Last created comment |
| `tag_id` | Last tag used/created |
| `tag_slug` | Slug for tag filter on List tasks |
