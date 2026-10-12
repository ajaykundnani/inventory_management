const currencyFormatter = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' })

export function toCents(amount) {
    return Math.round(Number(amount || 0) * 100)
}

export function formatCurrency(amount) {
    return currencyFormatter.format(Number(amount || 0))
}

export function formatCents(cents) {
    return currencyFormatter.format(cents / 100)
}

export function formatDateTime(value) {
    return value ? new Date(value).toLocaleString() : '—'
}

export function humanize(value) {
    if (!value) {
        return ''
    }
    const text = value.replaceAll('_', ' ')
    return text.charAt(0).toUpperCase() + text.slice(1)
}

export function firstError(errors, field) {
    return errors?.[field]?.[0] ?? ''
}
