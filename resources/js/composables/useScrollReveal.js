import { onMounted, onUnmounted } from 'vue'

const revealObserverOptions = {
  threshold: 0.12,
  rootMargin: '0px 0px -40px 0px',
}

let globalRevealObserver

const createRevealObserver = (selector = '.reveal') => {
  if (typeof window === 'undefined') {
    return null
  }

  if (!('IntersectionObserver' in window)) {
    document.querySelectorAll(selector).forEach((element) => {
      element.classList.add('visible')
    })

    return null
  }

  const observer = new IntersectionObserver((entries, currentObserver) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) {
        return
      }

      entry.target.classList.add('visible')
      currentObserver.unobserve(entry.target)
    })
  }, revealObserverOptions)

  document.querySelectorAll(selector).forEach((element) => {
    if (!element.classList.contains('visible')) {
      observer.observe(element)
    }
  })

  return observer
}

export const refreshScrollReveal = (selector = '.reveal') => {
  globalRevealObserver?.disconnect()
  globalRevealObserver = createRevealObserver(selector)
}

export function useScrollReveal(selector = '.reveal') {
  let observer

  onMounted(() => {
    observer = createRevealObserver(selector)
  })

  onUnmounted(() => observer?.disconnect())
}
