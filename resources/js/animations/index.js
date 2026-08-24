import { gsap } from 'gsap';

const animatedElements = document.querySelectorAll('[data-gsap="fade-in"]');

if (animatedElements.length > 0) {
    gsap.from(animatedElements, {
        autoAlpha: 0,
        duration: 0.7,
        ease: 'power2.out',
        stagger: 0.08,
        y: 16,
    });
}
