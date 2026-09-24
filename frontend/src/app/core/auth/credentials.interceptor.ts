import { HttpInterceptorFn, HttpErrorResponse } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';

/** Send session cookie + XSRF on same-origin API calls; redirect to login on 401. */
export const credentialsInterceptor: HttpInterceptorFn = (req, next) => {
  const router = inject(Router);
  const clone = req.url.startsWith('/api') ? req.clone({ withCredentials: true }) : req;
  return next(clone).pipe(
    catchError((err: HttpErrorResponse) => {
      if (err.status === 401 && !router.url.startsWith('/login')) {
        void router.navigate(['/login']);
      }
      return throwError(() => err);
    }),
  );
};
