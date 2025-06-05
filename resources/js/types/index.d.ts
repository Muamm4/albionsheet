import { LucideIcon } from 'lucide-react';
import type { Config } from 'ziggy-js';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: Config & { location: string };
    sidebarOpen: boolean;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}

// Interfaces para a nova estrutura de dados
export interface City {
  city: string;
  sell_price_min: number;
  sell_price_min_date: string;
  sell_price_max: number;
  sell_price_max_date: string;
  buy_price_min: number;
  buy_price_min_date: string;
  buy_price_max: number;
  buy_price_max_date: string;
}

export interface Quality {
  quality: number;
  cities: City[];
}

export interface Material {
  uniquename: string;
  nicename: string;
  amount: number;
  max_return_amount: number;
  shopcategory?: string;
  shopsubcategory1?: string;
  slottype?: string;
  qualities: Quality[];
}

export interface CraftingAnalysis {
  city: string;
  quality: number;
  material_cost: number;
  material_details: {
    id: number;
    uniquename: string;
    nicename: string;
    amount: number;
    unit_price: number;
    total_cost: number;
  }[];
  sell_price: number;
  profit: number;
  profit_margin: number;
  is_profitable: boolean;
  updated_at: string;
}

export interface AlbionItemData {
  id: number;
  uniquename: string;
  nicename: string;
  qualities: Quality[];
  materials: Material[];
  crafting_analysis: CraftingAnalysis[];
  crafting_requirements?: any;
  tier?: number;
  enchantment_level?: number;
  description?: string;
  shopcategory?: string;
  shopsubcategory1?: string;
  slottype?: string;
  [key: string]: any; // Para outros campos dinâmicos
}
