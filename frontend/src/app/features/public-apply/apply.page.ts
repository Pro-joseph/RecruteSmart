import { Component } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';

@Component({
  selector: 'app-apply',
  standalone: true,
  imports: [RouterLink],
  template: `
    <h1>Apply</h1>
    <p>Public application page (Lot 2). Token: {{ token }}</p>
    <p><a routerLink="/login">Recruiter login</a></p>
  `,
})
export class ApplyPage {
  token = '';
  constructor(route: ActivatedRoute) {
    this.token = route.snapshot.paramMap.get('token') ?? '';
  }
}
