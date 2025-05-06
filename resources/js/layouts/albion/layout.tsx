import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
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
            <div className="px-4 py-6">
                <Heading title={title} description={description} />

                <div className="flex flex-col space-y-8 lg:flex-row lg:space-y-0 lg:space-x-12 mt-8">
                    <aside className="w-full max-w-xl lg:w-48">
                        <nav className="flex flex-col space-y-1 space-x-0">
                            {sidebarNavItems.map((item, index) => (
                                <Button
                                    key={`${item.href}-${index}`}
                                    size="sm"
                                    variant="ghost"
                                    asChild
                                    className={cn('w-full justify-start', {
                                        'bg-muted': currentPath === item.href || 
                                                    (item.href === '/albion' && currentPath.startsWith('/albion/item/')),
                                    })}
                                >
                                    <Link href={item.href} prefetch>
                                        {item.title}
                                    </Link>
                                </Button>
                            ))}
                        </nav>
                    </aside>

                    <Separator className="my-6 md:hidden" />

                    <div className="flex-1 md:max-w-4xl">
                        <section className="space-y-8">{children}</section>
                    </div>
                </div>
            </div>
        </AppLayoutTemplate>
    );
}
