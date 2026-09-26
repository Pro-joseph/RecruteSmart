import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from './auth.service';

/** Recruiter area: requires a session (GET /auth/me). */
export const authGuard: CanActivateFn = async () => {
  const auth = inject(AuthService);
  const router = inject(Router);
  if (auth.user()) return true;
  const me = await auth.me();
  return me ? true : router.createUrlTree(['/login']);
};

/** Public auth pages: redirect to /app/offers when already logged in. */
export const guestGuard: CanActivateFn = async () => {
  const auth = inject(AuthService);
  const router = inject(Router);
  const me = auth.user() ?? (await auth.me());
  return me ? router.createUrlTree(['/app/offers']) : true;
};

/** Admin area (EF-1205): requires the session and the is_admin flag. */
export const adminGuard: CanActivateFn = async () => {
  const auth = inject(AuthService);
  const router = inject(Router);
  const me = auth.user() ?? (await auth.me());
  if (!me) return router.createUrlTree(['/login']);
  return me.is_admin ? true : router.createUrlTree(['/app/offers']);
};
