# UniCircle

A mobile app that puts campus life for university students in one place: study groups, a campus-only marketplace, and peer-to-peer micro-lending with a trust score.

Final Year Project, Antonine University (Faculty of Engineering and Technology). Supervisor: Dr. Ahmad Kassem. Students: Ahmad Shmoury, Mario Koubayati.

## Stack

| Layer | Technology |
| --- | --- |
| Mobile app | React Native (Expo) |
| Backend API | Laravel 13 (PHP 8.3), JWT auth (`php-open-source-saver/jwt-auth`) |
| Database | MySQL |
| AI / recommendations | Python + Flask (built last, after all other features) |

## Repository layout

```
backend/   Laravel REST API (/api/v1)
mobile/    Expo / React Native app
```

## Backend: run it locally

Requirements: PHP 8.3+, Composer, MySQL (XAMPP works).

```sh
cd backend
composer install
cp .env.example .env          # then set DB_* values to your MySQL
php artisan key:generate
php artisan jwt:secret
php artisan migrate
php artisan serve             # http://127.0.0.1:8000
```

Run the tests (uses in-memory SQLite, no MySQL needed):

```sh
cd backend
php artisan test
```

## API (v1)

Base path: `/api/v1`. Authenticated routes need `Authorization: Bearer <token>`.

| Method | Path | Auth | Description |
| --- | --- | --- | --- |
| POST | `/auth/register` | no | Create an account (university email only, `@ua.edu.lb`), returns a token and sends a verification email |
| POST | `/auth/login` | no | Exchange credentials for a token (5 attempts per minute) |
| POST | `/auth/logout` | yes | Invalidate the current token |
| POST | `/auth/refresh` | yes | Swap the token for a fresh one |
| GET | `/auth/email/verify/{id}/{hash}` | signed link | Confirm the email address from the verification mail |
| POST | `/auth/email/resend` | yes | Send the verification email again |
| GET | `/me` | yes | The authenticated user |
| PATCH | `/me` | yes | Update name, major, study year and bio |
| GET | `/courses` | yes | Course catalogue (`?search=`, paginated) |
| GET | `/me/courses` | yes | Courses the student is enrolled in |
| PUT | `/me/courses/{course}` | yes | Enroll in a course (idempotent) |
| DELETE | `/me/courses/{course}` | yes | Leave a course (idempotent) |

Success responses are wrapped in `data`. Errors return `message` (and `errors` for validation failures).

## Branch and review rules

Work on a feature branch (`feature/<name>`) and merge into `main` through a pull request reviewed by the other student.
