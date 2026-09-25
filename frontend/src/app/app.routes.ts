import { Routes } from '@angular/router';
import { adminGuard, authGuard, guestGuard } from './core/auth/auth.guard';

export const routes: Routes = [
  {
    path: 'login',
    loadComponent: () => import('./features/auth/login.page').then((m) => m.LoginPage),
    canActivate: [guestGuard],
  },
  {
    path: 'register',
    loadComponent: () => import('./features/auth/register.page').then((m) => m.RegisterPage),
    canActivate: [guestGuard],
  },
  {
    path: 'forgot-password',
    loadComponent: () =>
      import('./features/auth/forgot-password.page').then((m) => m.ForgotPasswordPage),
    canActivate: [guestGuard],
  },
  {
    path: 'apply/:token',
    loadComponent: () => import('./features/public-apply/apply.page').then((m) => m.ApplyPage),
  },
  {
    path: 'app/offers',
    loadComponent: () => import('./features/offers/offers.page').then((m) => m.OffersPage),
    canActivate: [authGuard],
  },
  {
    path: 'app/offers/new',
    loadComponent: () => import('./features/offers/offer-form.page').then((m) => m.OfferFormPage),
    canActivate: [authGuard],
  },
  {
    path: 'app/offers/:id/edit',
    loadComponent: () => import('./features/offers/offer-form.page').then((m) => m.OfferFormPage),
    canActivate: [authGuard],
  },
  {
    path: 'app/offers/:id',
    loadComponent: () =>
      import('./features/applications/workspace.page').then((m) => m.WorkspacePage),
    canActivate: [authGuard],
  },
  {
    path: 'app/offers/:id/applications/:appId',
    loadComponent: () =>
      import('./features/applications/candidate-fiche.page').then((m) => m.CandidateFichePage),
    canActivate: [authGuard],
  },
  {
    path: 'app/forwards',
    loadComponent: () =>
      import('./features/applications/forwards.page').then((m) => m.ForwardsPage),
    canActivate: [authGuard],
  },
  {
    path: 'app/settings',
    loadComponent: () => import('./features/offers/settings.page').then((m) => m.SettingsPage),
    canActivate: [authGuard],
  },
  {
    path: 'app/admin',
    loadComponent: () => import('./features/admin/admin.page').then((m) => m.AdminPage),
    canActivate: [adminGuard],
  },
  { path: '', redirectTo: '/app/offers', pathMatch: 'full' },
  { path: '**', redirectTo: '/app/offers' },
];
