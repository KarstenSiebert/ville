import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

export function useAuth() {
    const page = usePage()
    return computed(() => page.props.auth || { user: null })
}