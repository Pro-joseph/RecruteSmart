import { Directive, ElementRef, Input, OnChanges, SimpleChanges, inject } from '@angular/core';

/** Animates a number into place (count-up) when the input changes. */
@Directive({ selector: '[appCountUp]', standalone: true })
export class CountUpDirective implements OnChanges {
  @Input('appCountUp') value: number | null = 0;
  @Input() countUpDuration = 650;

  private readonly el = inject(ElementRef<HTMLElement>);
  private raf = 0;
  private shown = 0;

  ngOnChanges(changes: SimpleChanges): void {
    if (!changes['value']) {
      return;
    }

    cancelAnimationFrame(this.raf);
    const target = this.value ?? 0;
    const from = this.shown;

    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduced || from === target) {
      this.render(target);
      return;
    }

    const start = performance.now();
    const duration = this.countUpDuration;

    const tick = (now: number): void => {
      const t = Math.min(1, (now - start) / duration);
      const eased = 1 - Math.pow(1 - t, 3); // ease-out cubic
      const current = from + (target - from) * eased;
      this.render(current);
      if (t < 1) {
        this.raf = requestAnimationFrame(tick);
      }
    };

    this.raf = requestAnimationFrame(tick);
  }

  private render(n: number): void {
    this.shown = Math.round(n);
    this.el.nativeElement.textContent = String(this.shown);
  }
}
