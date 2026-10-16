import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import Lenis from 'lenis';
import 'lenis/dist/lenis.css';
import { onBeforeUnmount, onMounted } from 'vue';

gsap.registerPlugin(ScrollTrigger);

/**
 * Smooth, inertial scrolling for the marketing pages, kept in step with
 * ScrollTrigger so pinned sections stay in sync. Never used inside the app,
 * and switched off when the visitor asks for reduced motion.
 */
export function useSmoothScroll(): void {
    let lenis: Lenis | undefined;

    const raf = (time: number) => lenis?.raf(time * 1000);

    onMounted(() => {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        lenis = new Lenis({ anchors: true });
        lenis.on('scroll', ScrollTrigger.update);
        gsap.ticker.add(raf);
        gsap.ticker.lagSmoothing(0);
    });

    onBeforeUnmount(() => {
        gsap.ticker.remove(raf);
        lenis?.destroy();
        lenis = undefined;
    });
}
