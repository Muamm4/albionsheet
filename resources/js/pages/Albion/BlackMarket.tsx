import React, { useState, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import LoadingIndicator from '@/components/LoadingIndicator';
import { 
    Table, 
    TableBody, 
    TableCaption, 
    TableCell, 
    TableHead, 
    TableHeader, 
    TableRow 
} from '@/components/ui/table';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { ArrowUpDown, RefreshCw } from 'lucide-react';
import AlbionLayout from '@/layouts/albion/layout';

interface ItemPrice {
    id: number;
    item_id: number;
    uniquename: string;
    nicename: string;
    tier: number;
    enchantment_level: number;
    quality: number;
    city: string;
    sell_price_min: number;
    buy_price_min: number;
    black_market_price: number;
    profit: number;
    profit_percentage: number;
    updated_at: string;
}

export default function BlackMarket() {
    const [items, setItems] = useState<ItemPrice[]>([]);
    const [loading, setLoading] = useState<boolean>(true);
    const [error, setError] = useState<string | null>(null);
    const [sortField, setSortField] = useState<string>('profit_percentage');
    const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('desc');
    const [minProfit, setMinProfit] = useState<number>(0);
    const [selectedCity, setSelectedCity] = useState<string>('all');
    const [minTier, setMinTier] = useState<number>(4);
    const [searchTerm, setSearchTerm] = useState<string>('');
    const [refreshing, setRefreshing] = useState<boolean>(false);

    const cities = [
        { value: 'all', label: 'Todas Cidades' },
        { value: 'Bridgewatch', label: 'Bridgewatch' },
        { value: 'Fort Sterling', label: 'Fort Sterling' },
        { value: 'Lymhurst', label: 'Lymhurst' },
        { value: 'Martlock', label: 'Martlock' },
        { value: 'Thetford', label: 'Thetford' },
        { value: 'Caerleon', label: 'Caerleon' },
        { value: 'Brecilien', label: 'Brecilien' },
    ];

    const tiers = [
        { value: 1, label: 'T1+' },
        { value: 2, label: 'T2+' },
        { value: 3, label: 'T3+' },
        { value: 4, label: 'T4+' },
        { value: 5, label: 'T5+' },
        { value: 6, label: 'T6+' },
        { value: 7, label: 'T7+' },
        { value: 8, label: 'T8+' },
    ];

    const fetchBlackMarketData = async () => {
        setLoading(true);
        setError(null);
        setRefreshing(true);
        
        try {
            const response = await axios.get('/api/albion/black-market-opportunities');
            setItems(response.data);
        } catch (err) {
            setError('Erro ao carregar dados do Black Market. Tente novamente mais tarde.');
            console.error('Erro ao buscar dados do Black Market:', err);
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    };

    useEffect(() => {
        fetchBlackMarketData();
    }, []);

    const handleSort = (field: string) => {
        if (sortField === field) {
            setSortDirection(sortDirection === 'asc' ? 'desc' : 'asc');
        } else {
            setSortField(field);
            setSortDirection('desc');
        }
    };

    const formatPrice = (price: number) => {
        return new Intl.NumberFormat('pt-BR').format(price);
    };

    const formatProfitPercentage = (percentage: number) => {
        return `${percentage.toFixed(2)}%`;
    };

    const getQualityColor = (quality: number) => {
        switch (quality) {
            case 1: return 'bg-gray-200 text-gray-800'; // Normal
            case 2: return 'bg-green-200 text-green-800'; // Good
            case 3: return 'bg-blue-200 text-blue-800'; // Outstanding
            case 4: return 'bg-purple-200 text-purple-800'; // Excellent
            case 5: return 'bg-yellow-200 text-yellow-800'; // Masterpiece
            default: return 'bg-gray-200 text-gray-800';
        }
    };

    const getQualityName = (quality: number) => {
        switch (quality) {
            case 1: return 'Normal';
            case 2: return 'Good';
            case 3: return 'Outstanding';
            case 4: return 'Excellent';
            case 5: return 'Masterpiece';
            default: return 'Desconhecido';
        }
    };

    const getTierName = (tier: number, enchantment: number) => {
        return `T${tier}${enchantment > 0 ? '.' + enchantment : ''}`;
    };

    const filteredItems = items
        .filter(item => item.profit >= minProfit)
        .filter(item => selectedCity === 'all' || item.city === selectedCity)
        .filter(item => item.tier >= minTier)
        .filter(item => {
            if (!searchTerm) return true;
            const search = searchTerm.toLowerCase();
            return (
                (item.nicename && item.nicename.toLowerCase().includes(search)) ||
                (item.uniquename && item.uniquename.toLowerCase().includes(search))
            );
        });

    const sortedItems = [...filteredItems].sort((a, b) => {
        let valueA = a[sortField as keyof ItemPrice];
        let valueB = b[sortField as keyof ItemPrice];
        
        if (typeof valueA === 'string' && typeof valueB === 'string') {
            return sortDirection === 'asc' 
                ? valueA.localeCompare(valueB) 
                : valueB.localeCompare(valueA);
        }
        
        return sortDirection === 'asc' 
            ? (valueA as number) - (valueB as number) 
            : (valueB as number) - (valueA as number);
    });

    return (
        <AlbionLayout title="Black Market - Oportunidades de Flipping" description="Encontre itens para comprar nas cidades e vender com lucro no Black Market">
            <Head title="Black Market - Oportunidades de Flipping" />
            <div className="container mx-auto py-6">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl font-bold">Black Market - Oportunidades de Flipping</CardTitle>
                        <CardDescription>
                            Encontre itens para comprar nas cidades e vender com lucro no Black Market
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-col md:flex-row gap-4 mb-6">
                            <div className="w-full md:w-1/4">
                                <label className="block text-sm font-medium mb-1">Cidade</label>
                                <Select 
                                    value={selectedCity} 
                                    onValueChange={setSelectedCity}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Selecione a cidade" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {cities.map(city => (
                                            <SelectItem key={city.value} value={city.value}>
                                                {city.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="w-full md:w-1/4">
                                <label className="block text-sm font-medium mb-1">Tier Mínimo</label>
                                <Select 
                                    value={minTier.toString()} 
                                    onValueChange={(value) => setMinTier(parseInt(value))}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Selecione o tier mínimo" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {tiers.map(tier => (
                                            <SelectItem key={tier.value} value={tier.value.toString()}>
                                                {tier.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="w-full md:w-1/4">
                                <label className="block text-sm font-medium mb-1">Lucro Mínimo</label>
                                <Input
                                    type="number"
                                    value={minProfit}
                                    onChange={(e) => setMinProfit(parseInt(e.target.value) || 0)}
                                    placeholder="Lucro mínimo"
                                />
                            </div>
                            <div className="w-full md:w-1/4">
                                <label className="block text-sm font-medium mb-1">Buscar</label>
                                <Input
                                    type="text"
                                    value={searchTerm}
                                    onChange={(e) => setSearchTerm(e.target.value)}
                                    placeholder="Nome do item"
                                />
                            </div>
                        </div>
                        
                        <div className="flex justify-between items-center mb-4">
                            <div className="text-sm text-muted-foreground">
                                {filteredItems.length} itens encontrados
                            </div>
                            <Button 
                                onClick={fetchBlackMarketData} 
                                disabled={refreshing}
                                variant="outline"
                                size="sm"
                            >
                                {refreshing ? (
                                    <>
                                        <RefreshCw className="mr-2 h-4 w-4 animate-spin" />
                                        Atualizando...
                                    </>
                                ) : (
                                    <>
                                        <RefreshCw className="mr-2 h-4 w-4" />
                                        Atualizar Dados
                                    </>
                                )}
                            </Button>
                        </div>
                        
                        {loading && !refreshing ? (
                            <div className="flex justify-center py-10">
                                <LoadingIndicator />
                            </div>
                        ) : error ? (
                            <div className="text-center py-10 text-red-500">{error}</div>
                        ) : (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableCaption>Lista de oportunidades de flipping para o Black Market</TableCaption>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-[120px]">
                                                <div 
                                                    className="flex items-center cursor-pointer"
                                                    onClick={() => handleSort('tier')}
                                                >
                                                    Tier
                                                    {sortField === 'tier' && (
                                                        <ArrowUpDown className="ml-2 h-4 w-4" />
                                                    )}
                                                </div>
                                            </TableHead>
                                            <TableHead>
                                                <div 
                                                    className="flex items-center cursor-pointer"
                                                    onClick={() => handleSort('nicename')}
                                                >
                                                    Item
                                                    {sortField === 'nicename' && (
                                                        <ArrowUpDown className="ml-2 h-4 w-4" />
                                                    )}
                                                </div>
                                            </TableHead>
                                            <TableHead>Qualidade</TableHead>
                                            <TableHead>
                                                <div 
                                                    className="flex items-center cursor-pointer"
                                                    onClick={() => handleSort('city')}
                                                >
                                                    Cidade
                                                    {sortField === 'city' && (
                                                        <ArrowUpDown className="ml-2 h-4 w-4" />
                                                    )}
                                                </div>
                                            </TableHead>
                                            <TableHead className="text-right">
                                                <div 
                                                    className="flex items-center justify-end cursor-pointer"
                                                    onClick={() => handleSort('sell_price_min')}
                                                >
                                                    Preço Compra
                                                    {sortField === 'sell_price_min' && (
                                                        <ArrowUpDown className="ml-2 h-4 w-4" />
                                                    )}
                                                </div>
                                            </TableHead>
                                            <TableHead className="text-right">
                                                <div 
                                                    className="flex items-center justify-end cursor-pointer"
                                                    onClick={() => handleSort('black_market_price')}
                                                >
                                                    Preço Black Market
                                                    {sortField === 'black_market_price' && (
                                                        <ArrowUpDown className="ml-2 h-4 w-4" />
                                                    )}
                                                </div>
                                            </TableHead>
                                            <TableHead className="text-right">
                                                <div 
                                                    className="flex items-center justify-end cursor-pointer"
                                                    onClick={() => handleSort('profit')}
                                                >
                                                    Lucro
                                                    {sortField === 'profit' && (
                                                        <ArrowUpDown className="ml-2 h-4 w-4" />
                                                    )}
                                                </div>
                                            </TableHead>
                                            <TableHead className="text-right">
                                                <div 
                                                    className="flex items-center justify-end cursor-pointer"
                                                    onClick={() => handleSort('profit_percentage')}
                                                >
                                                    Margem
                                                    {sortField === 'profit_percentage' && (
                                                        <ArrowUpDown className="ml-2 h-4 w-4" />
                                                    )}
                                                </div>
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {sortedItems.length > 0 ? (
                                            sortedItems.map((item) => (
                                                <TableRow key={`${item.item_id}-${item.quality}-${item.city}`}>
                                                    <TableCell className="font-medium">
                                                        {getTierName(item.tier, item.enchantment_level)}
                                                    </TableCell>
                                                    <TableCell>
                                                        <a 
                                                            href={`/albion/item/${item.item_id}`} 
                                                            className="text-blue-600 hover:underline"
                                                        >
                                                            {item.nicename || item.uniquename}
                                                        </a>
                                                    </TableCell>
                                                    <TableCell>
                                                        <Badge className={getQualityColor(item.quality)}>
                                                            {getQualityName(item.quality)}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell>{item.city}</TableCell>
                                                    <TableCell className="text-right">
                                                        {formatPrice(item.sell_price_min)}
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        {formatPrice(item.black_market_price)}
                                                    </TableCell>
                                                    <TableCell className="text-right font-medium">
                                                        {formatPrice(item.profit)}
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        <Badge variant={item.profit_percentage >= 20 ? "success" : "default"}>
                                                            {formatProfitPercentage(item.profit_percentage)}
                                                        </Badge>
                                                    </TableCell>
                                                </TableRow>
                                            ))
                                        ) : (
                                            <TableRow>
                                                <TableCell colSpan={8} className="text-center py-4">
                                                    Nenhum item encontrado com os filtros atuais
                                                </TableCell>
                                            </TableRow>
                                        )}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AlbionLayout>
    );
}
