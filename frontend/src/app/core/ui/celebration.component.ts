import { Component, EventEmitter, OnInit, Output, signal } from '@angular/core';

const COLORS = ['#0f5c46', '#a35a07', '#2856a8', '#a52a1f', '#4b3fa7', '#0f766e'];

interface Piece {
  i: number;
  x: string;
  c: string;
  d: string;
  r: string;
  drift: string;
}

/** One-shot confetti burst. Emits `finished` when the animation window is over. */
@Component({
  selector: 'app-celebration',
  standalone: true,
  template: `<span class="celebration" aria-hidden="true">
    @for (p of pieces(); track p.i) {
      <i
        [style.--x]="p.x"
        [style.--c]="p.c"
        [style.--d]="p.d"
        [style.--r]="p.r"
        [style.--drift]="p.drift"
      ></i>
    }
  </span>`,
})
export class CelebrationComponent implements OnInit {
  @Output() finished = new EventEmitter<void>();

  readonly pieces = signal<Piece[]>([]);

  ngOnInit(): void {
    const pieces: Piece[] = [];
    for (let i = 0; i < 70; i++) {
      pieces.push({
        i,
        x: `${Math.random() * 100}%`,
        c: COLORS[i % COLORS.length],
        d: `${Math.random() * 0.5}s`,
        r: `${360 + Math.random() * 720}deg`,
        drift: `${(Math.random() - 0.5) * 160}px`,
      });
    }
    this.pieces.set(pieces);
    setTimeout(() => this.finished.emit(), 2400);
  }
}
