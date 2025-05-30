import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';
import { type NavItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';
import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Consulta de Preços',
        href: '/albion',
        icon: null,
    },
    {
        title: 'Itens Favoritos',
        href: '/albion/favorites',
        icon: null,
    },
    {
        title: 'Calculadora de Lucro',
        href: '/albion/calculator',
        icon: null,
    },
    {
        title: 'Mercado Black',
        href: '/albion/black-market',
        icon: null,
    },
];

interface AlbionLayoutProps extends PropsWithChildren {
    title: string;
    description: string;
    customBreadcrumbs?: Array<{
        title: string;
        href: string;
    }>;
}

export default function AlbionLayout({ 
    children, 
    title, 
    description, 
    customBreadcrumbs 
}: AlbionLayoutProps) {
    // When server-side rendering, we only render the layout on the client...
    if (typeof window === 'undefined') {
        return null;
    }

    const currentPath = window.location.pathname;
    
    // Breadcrumbs para navegação
    const breadcrumbs = customBreadcrumbs || [
        {
            title: 'Albion Online',
            href: '/albion',
        },
        {
            title: title,
            href: currentPath,
        },
    ];

    return (
        <AppLayoutTemplate breadcrumbs={breadcrumbs}>
            <Head title={title} >
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
            </Head>
            <div className="px-4 py-6">
                <Heading title={title} description={description} />

                <div className="flex flex-col space-y-8 lg:flex-row lg:space-y-0 lg:space-x-12 mt-8 justify-center">
                    <div className="flex-1">
                        <section className="space-y-8">{children}</section>
                    </div>
                </div>
            </div>
        </AppLayoutTemplate>
    );
}
