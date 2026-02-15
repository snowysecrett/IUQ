export function statusBadgeClass(status) {
    if (status === 'draft') {
        return 'bg-yellow-100 text-yellow-800 border-yellow-200';
    }

    if (status === 'live') {
        return 'bg-red-100 text-red-800 border-red-200';
    }

    if (status === 'completed') {
        return 'bg-green-100 text-green-800 border-green-200';
    }

    return 'bg-gray-100 text-gray-700 border-gray-200';
}
