import { HttpInterceptorFn, HttpErrorResponse } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';

// ponytail: single-flight redirect — during the login navigation router.url is still the
// old URL, so without this flag every 401 from a guard restarts the navigation (storm).
let redirectingToLogin = false;

/** Send session cookie + XSRF on same-origin API calls; redirect to login on 401. */
export const credentialsInterceptor: HttpInterceptorFn = (req, next) => {
  const router = inject(Router);
  const clone = req.url.startsWith('/api') ? req.clone({ withCredentials: true }) : req;
  return next(clone).pipe(
    catchError((err: HttpErrorResponse) => {
      if (err.status === 401 && !router.url.startsWith('/login') && !redirectingToLogin) {
        redirectingToLogin = true;
        void router.navigate(['/login']).finally(() => {
          redirectingToLogin = false;
        });
      }
      return throwError(() => err);
    }),
  );
};
