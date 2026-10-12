export const autofocus = {
    mounted(element) {
        requestAnimationFrame(() => element.focus({ preventScroll: true }))
    },
}
