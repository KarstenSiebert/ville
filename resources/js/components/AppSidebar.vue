<script setup lang="ts">
import { ref, onMounted, watch } from "vue";
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import deposits from '@/routes/deposits';
import archive from '@/routes/archives';
import markets from '@/routes/markets';
import history from '@/routes/history';
import users from '@/routes/users';
import messages from '@/routes/log';
import onchain from '@/routes/onchain';
import reconciliation from '@/routes/reconciliation';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { House, Archive, Landmark, History, Coins, Link as Chain, Logs } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import { is, permissionsLoaded, reloadRolesAndPermissions } from 'laravel-permission-to-vuejs'

const mainAdminNavItems: NavItem[] = [
    {
        title: 'wallet',
        href: deposits.index(),
        icon: House,
    },
    {
        title: 'onchain',
        href: onchain.index(),
        icon: Chain,
    },
    {
        title: 'outgoings',
        href: users.outgoings(),
        icon: Coins,
    },
    {
        title: 'markets',
        href: markets.index(),
        icon: Landmark,
    },
    {
        title: 'archive',
        href: archive.index(),
        icon: Archive,
    },
    {
        title: 'chain_history',
        href: history.index(),
        icon: History,
    },
    {
        title: 'reconciliation',
        href: reconciliation.index(),
        icon: Landmark,
    },
    {
        title: 'log_messages',
        href: messages.index(),
        icon: Logs,
    },
];

const mainUserNavItems: NavItem[] = [
    {
        title: 'wallet',
        href: deposits.index(),
        icon: House,
    },
    {
        title: 'markets',
        href: markets.index(),
        icon: Landmark,
    },
    {
        title: 'archive',
        href: archive.index(),
        icon: Archive,
    }
];

const footerNavItems: NavItem[] = [

];

const navItems = ref<NavItem[]>([])

watch(permissionsLoaded, (loaded) => {
    if (loaded) {
        updateNavItems()
    }
})

function updateNavItems() {
    if (is('superadmin|admin')) {
        navItems.value = mainAdminNavItems
    } else if (is('parent')) {
        navItems.value = mainUserNavItems
    } else {
        navItems.value = mainUserNavItems
    }
}

onMounted(() => {

    setTimeout(async () => {
        await reloadRolesAndPermissions()
        updateNavItems()
        permissionsLoaded.value = true
    }, 0)

    if (permissionsLoaded.value == true) {
        updateNavItems()
    }
})
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="navItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
