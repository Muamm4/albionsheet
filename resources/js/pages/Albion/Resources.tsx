import React, { useState, useEffect, useMemo } from 'react';
import axios from 'axios';
import { 
  formatPrice, 
  getItemIconUrl, 
  getItemTier,
  getEnchantmentLevel,
  ALBION_CITIES
} from '@/utils/albionUtils';
import AlbionLayout from '@/layouts/albion/layout';
import { Separator } from '@/components/ui/separator';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { ArrowUp, ArrowDown, ArrowUpDown, RefreshCcw } from 'lucide-react';
import { Button } from '@/components/ui/button';

interface ResourcePrice {
  [city: string]: number | null;
}

interface ResourcePriceDate {
  [city: string]: string | null;
}

interface FlippingData {
  buy_city: string;
  buy_price: number;
  sell_city: string;
  sell_price: number;
  profit: number;
  profit_percentage: number;
}

interface Resource {
  id: number;
  uniquename: string;
  nicename: string;
  tier: number;
  enchantment_level: number;
  shop_category: string;
  shop_subcategory1: string;
  prices: ResourcePrice;
  price_dates: ResourcePriceDate;
  min_price: number | null;
  max_price: number | null;
  min_city: string;
  max_city: string;
  flipping: FlippingData | null;
}

export default function Resources() {
  const [resources, setResources] = useState<Resource[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [sortField, setSortField] = useState<string>('tier');
  const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('asc');
  const [activeTab, setActiveTab] = useState<string>('all');
  const [activeSubcategory, setActiveSubcategory] = useState<string>('all');
  const [activeEnchantmentLevel, setActiveEnchantmentLevel] = useState<string>('all');

  useEffect(() => {
    fetchResources();
  }, []);

  const fetchResources = async () => {
    setLoading(true);
    try {
      const response = await axios.get('/api/albion/resources');
      setResources(response.data);
      setError(null);
    } catch (err) {
      console.error('Erro ao buscar recursos:', err);
      setError('Não foi possível carregar os dados dos recursos. Tente novamente mais tarde.');
    } finally {
      setLoading(false);
    }
  };

  const sortResources = (field: string) => {
    if (sortField === field) {
      // Inverter direção se o mesmo campo for clicado novamente
      setSortDirection(sortDirection === 'asc' ? 'desc' : 'asc');
    } else {
      // Novo campo, definir como ascendente
      setSortField(field);
      setSortDirection('asc');
    }
  };

  const getSortIcon = (field: string) => {
    if (sortField !== field) return <ArrowUpDown className="h-4 w-4 text-muted-foreground" />;
    return sortDirection === 'asc' 
      ? <ArrowUp className="h-4 w-4" /> 
      : <ArrowDown className="h-4 w-4" />;
  };

  // Extrair subcategorias únicas dos recursos
  const uniqueSubcategories = useMemo(() => {
    console.log(resources)
    const subcategories = resources
      .map(resource => resource.shop_subcategory1)
      .filter((value, index, self) => 
        value && self.indexOf(value) === index
      );
    return subcategories.sort();
  }, [resources]);

  const filteredAndSortedResources = resources
    .filter(resource => {
      // Filtro por tier
      if (activeTab !== 'all' && resource.tier !== parseInt(activeTab, 10)) {
        return false;
      }
      
      // Filtro por subcategoria
      if (activeSubcategory !== 'all' && resource.shop_subcategory1 !== activeSubcategory) {
        return false;
      }

      if (activeEnchantmentLevel !== 'all' && resource.enchantment_level !== parseInt(activeEnchantmentLevel, 10)) {
        return false;
      }
      
      return true;
    })
    .sort((a, b) => {
      let comparison = 0;
      
      switch (sortField) {
        case 'tier':
          comparison = (a.tier || 0) - (b.tier || 0);
          break;
        case 'name':
          comparison = (a.nicename || '').localeCompare(b.nicename || '');
          break;
        case 'minPrice':
          const aMin = a.min_price || Number.MAX_SAFE_INTEGER;
          const bMin = b.min_price || Number.MAX_SAFE_INTEGER;
          comparison = aMin - bMin;
          break;
        case 'maxPrice':
          const aMax = a.max_price || 0;
          const bMax = b.max_price || 0;
          comparison = aMax - bMax;
          break;
        case 'profit':
          const aProfit = a.flipping?.profit || 0;
          const bProfit = b.flipping?.profit || 0;
          comparison = aProfit - bProfit;
          break;
        case 'profitPercentage':
          const aProfitPct = a.flipping?.profit_percentage || 0;
          const bProfitPct = b.flipping?.profit_percentage || 0;
          comparison = aProfitPct - bProfitPct;
          break;
        default:
          comparison = 0;
      }
      
      return sortDirection === 'asc' ? comparison : -comparison;
    });

  // Calcular estatísticas gerais
  const bestFlippingOpportunities = [...resources]
    .filter(r => r.flipping !== null)
    .sort((a, b) => (b.flipping?.profit_percentage || 0) - (a.flipping?.profit_percentage || 0))
    .slice(0, 5);

  const formatDate = (dateString: string | null) => {
    if (!dateString) return null;
    
    try {
      const date = new Date(dateString);
      return date.toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
    } catch (e) {
      return null;
    }
  };

  const renderPriceCell = (price: number | null, city: string, resource: Resource) => {
    const isMin = resource.min_city === city && price === resource.min_price;
    const isMax = resource.max_city === city && price === resource.max_price;
    const dateString = resource.price_dates?.[city] || null;
    const formattedDate = formatDate(dateString);
    
    return (
      <TableCell 
        className={`text-right ${isMin ? 'bg-green-50 dark:bg-green-950' : ''} ${isMax ? 'bg-red-50 dark:bg-red-950' : ''}`}
      >
        {price ? (
          <div className="flex flex-col items-end">
            <span className={`font-medium ${isMin ? 'text-green-600 dark:text-green-400' : ''} ${isMax ? 'text-red-600 dark:text-red-400' : ''}`}>
              {formatPrice(price)}
            </span>
            {formattedDate && (
              <span className="text-xs text-muted-foreground mt-1">
                {formattedDate}
              </span>
            )}
          </div>
        ) : (
          <span className="text-muted-foreground">-</span>
        )}
      </TableCell>
    );
  };

  return (
    <AlbionLayout 
      title="Recursos" 
      description="Análise de preços dos recursos do Albion Online por cidade"
    >
      <div className="space-y-6">
        {loading ? (
          <div className="space-y-4">
            <Skeleton className="h-8 w-full" />
            <Skeleton className="h-64 w-full" />
          </div>
        ) : error ? (
          <div className="rounded-md bg-red-50 p-4 dark:bg-red-950">
            <p className="text-red-600 dark:text-red-400">{error}</p>
          </div>
        ) : (
          <>
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
              <Card>
                <CardHeader className="pb-2">
                  <CardTitle>Total de Recursos</CardTitle>
                  <CardDescription>Recursos disponíveis para análise</CardDescription>
                </CardHeader>
                <CardContent>
                  <div className="text-2xl font-bold">{resources.length}</div>
                </CardContent>
              </Card>
              
              <Card>
                <CardHeader className="pb-2">
                  <CardTitle>Melhor Oportunidade</CardTitle>
                  <CardDescription>Maior margem de lucro potencial</CardDescription>
                </CardHeader>
                <CardContent>
                 {bestFlippingOpportunities.length > 0 ? (
                    <div className="space-y-2">
                      <div className="flex items-center space-x-2">
                        <img 
                          src={getItemIconUrl(bestFlippingOpportunities[0].uniquename, 40)} 
                          alt={bestFlippingOpportunities[0].nicename} 
                          className="h-10 w-10 rounded bg-gray-100 dark:bg-gray-800 object-contain"
                          onError={(e) => {
                            (e.target as HTMLImageElement).src = 'https://render.albiononline.com/v1/item/T4_BAG.png?size=40&quality=1';
                          }}
                        />
                        <div>
                          <div className="font-medium">{bestFlippingOpportunities[0].nicename}</div>
                          <div className="text-xs text-muted-foreground">
                            T{bestFlippingOpportunities[0].tier}
                            {bestFlippingOpportunities[0].enchantment_level > 0 && `.${bestFlippingOpportunities[0].enchantment_level}`}
                          </div>
                        </div>
                      </div>
                      
                      <div className="text-2xl font-bold text-green-600 dark:text-green-400">
                        {bestFlippingOpportunities[0].flipping?.profit_percentage.toFixed(2)}%
                      </div>
                      
                      <div className="grid grid-cols-2 gap-2 text-sm">
                        <div className="rounded-md bg-muted p-2">
                          <div className="font-medium">Compra</div>
                          <div>{bestFlippingOpportunities[0].flipping?.buy_city}</div>
                          <div className="font-bold">{formatPrice(bestFlippingOpportunities[0].flipping?.buy_price || 0)}</div>
                          {bestFlippingOpportunities[0].price_dates && 
                           bestFlippingOpportunities[0].price_dates[bestFlippingOpportunities[0].flipping?.buy_city || ''] && (
                            <div className="text-xs text-muted-foreground mt-1">
                              {formatDate(bestFlippingOpportunities[0].price_dates[bestFlippingOpportunities[0].flipping?.buy_city || ''])}
                            </div>
                          )}
                        </div>
                        <div className="rounded-md bg-muted p-2">
                          <div className="font-medium">Venda</div>
                          <div>{bestFlippingOpportunities[0].flipping?.sell_city}</div>
                          <div className="font-bold">{formatPrice(bestFlippingOpportunities[0].flipping?.sell_price || 0)}</div>
                          {bestFlippingOpportunities[0].price_dates && 
                           bestFlippingOpportunities[0].price_dates[bestFlippingOpportunities[0].flipping?.sell_city || ''] && (
                            <div className="text-xs text-muted-foreground mt-1">
                              {formatDate(bestFlippingOpportunities[0].price_dates[bestFlippingOpportunities[0].flipping?.sell_city || ''])}
                            </div>
                          )}
                        </div>
                      </div>
                      
                      <div className="text-xs text-muted-foreground">
                        Lucro: {formatPrice(bestFlippingOpportunities[0].flipping?.profit || 0)}
                      </div>
                    </div>
                  ) : (
                    <div className="text-muted-foreground">Nenhuma oportunidade encontrada</div>
                  )}
                </CardContent>
              </Card>
              
              <Card>
                <CardHeader className="pb-2">
                  <CardTitle>Última Atualização</CardTitle>
                  <CardDescription>Dados de preços do mercado</CardDescription>
                </CardHeader>
                <CardContent>
                  <div className="text-md font-medium">
                    {(() => {
                      // Encontrar a data mais recente entre todos os preços
                      let latestDate: Date | null = null;
                      let formattedDate = 'Sem dados de atualização';
                      
                      resources.forEach(resource => {
                        if (resource.price_dates) {
                          Object.values(resource.price_dates).forEach((dateString: string | null) => {
                            if (dateString) {
                              try {
                                const date = new Date(dateString);
                                if (!isNaN(date.getTime()) && (!latestDate || date > latestDate)) {
                                  latestDate = date;
                                }
                              } catch (e) {
                                console.error('Erro ao converter data:', dateString);
                              }
                            }
                          });
                        }
                      });
                      
                      if (latestDate) {
                        try {
                          formattedDate = new Intl.DateTimeFormat('pt-BR', {
                            day: '2-digit',
                            month: '2-digit',
                            year: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                          }).format(latestDate);
                        } catch (e) {
                          console.error('Erro ao formatar data:', e);
                          formattedDate = 'Data indisponível';
                        }
                      }
                      
                      return formattedDate;
                    })()}
                  </div>
                  <button 
                    onClick={fetchResources}
                    className="mt-2 text-sm text-blue-600 hover:underline dark:text-blue-400"
                  >
                    <RefreshCcw className="inline-block mr-1 h-3 w-3" /> Atualizar dados
                  </button>
                </CardContent>
              </Card>
            </div>

            <Separator />

            <div className="space-y-4">
              <div className="flex items-center mb-2">
                <span className="text-sm font-medium mr-2">Tier:</span>
                <Tabs defaultValue="all" value={activeTab} onValueChange={setActiveTab}>
                  <TabsList>
                    <TabsTrigger value="all">Todos</TabsTrigger>
                    <TabsTrigger value="2">T2</TabsTrigger>
                    <TabsTrigger value="3">T3</TabsTrigger>
                    <TabsTrigger value="4">T4</TabsTrigger>
                    <TabsTrigger value="5">T5</TabsTrigger>
                    <TabsTrigger value="6">T6</TabsTrigger>
                    <TabsTrigger value="7">T7</TabsTrigger>
                    <TabsTrigger value="8">T8</TabsTrigger>
                  </TabsList>
                </Tabs>
              </div>
              
              <div className="flex items-center mb-2">
                <span className="text-sm font-medium mr-2">Subcategoria:</span>
                <Tabs defaultValue="all" value={activeSubcategory} onValueChange={setActiveSubcategory}>
                  <TabsList>
                    <TabsTrigger value="all">Todas</TabsTrigger>
                    {uniqueSubcategories.map(subcategory => (
                      <TabsTrigger key={subcategory} value={subcategory}>
                        {subcategory.charAt(0).toUpperCase() + subcategory.slice(1)}
                      </TabsTrigger>
                    ))}
                  </TabsList>
                </Tabs>
              </div>
              <div className="flex items-center mb-2">
                <span className="text-sm font-medium mr-2">Enchantamento:</span>
                <Tabs defaultValue="all" value={activeEnchantmentLevel} onValueChange={setActiveEnchantmentLevel}>
                  <TabsList>
                    <TabsTrigger value="all">Todos</TabsTrigger>
                    <TabsTrigger value="1">1</TabsTrigger>
                    <TabsTrigger value="2">2</TabsTrigger>
                    <TabsTrigger value="3">3</TabsTrigger>
                    <TabsTrigger value="4">4</TabsTrigger>
                  </TabsList>
                </Tabs>
              </div>
              <div className="mt-4">
                <Card>
                  <CardHeader className="flex-row items-center justify-between">
                    <div>
                      <CardTitle>Preços dos Recursos por Cidade</CardTitle>
                      <CardDescription>
                        Preços mínimos de venda. Destacados em <span className="text-green-600 dark:text-green-400 font-medium">verde</span> os menores preços e em <span className="text-red-600 dark:text-red-400 font-medium">vermelho</span> os maiores preços.
                      </CardDescription>
                    </div>
                    <div className="flex items-center justify-end">
                      <Button
                        size="sm"
                        onClick={() => {
                          setActiveTab('all');
                          setActiveSubcategory('all');
                          sortResources('');
                        }}
                      >
                        <RefreshCcw className="mr-1 h-4 w-4" /> Resetar Filtros
                      </Button>
                    </div>

                  </CardHeader>
                  <CardContent>
                    <div className="overflow-x-auto">
                      <Table>
                        <TableHeader>
                          <TableRow>
                            <TableHead className="w-[240px] cursor-pointer" onClick={() => sortResources('name')}>
                              <div className="flex items-center space-x-1">
                                <span>Recurso</span>
                                {getSortIcon('name')}
                              </div>
                            </TableHead>
                            <TableHead className="w-[80px] cursor-pointer" onClick={() => sortResources('tier')}>
                              <div className="flex items-center space-x-1">
                                <span>Tier</span>
                                {getSortIcon('tier')}
                              </div>
                            </TableHead>
                            {ALBION_CITIES.map(city => (
                              <TableHead key={city} className="text-right">
                                {city}
                              </TableHead>
                            ))}
                            <TableHead 
                              className="text-right cursor-pointer" 
                              onClick={() => sortResources('profitPercentage')}
                            >
                              <div className="flex items-center justify-end space-x-1">
                                <span>Flipping</span>
                                {getSortIcon('profitPercentage')}
                              </div>
                            </TableHead>
                          </TableRow>
                        </TableHeader>
                        <TableBody>
                          {filteredAndSortedResources.length === 0 ? (
                            <TableRow>
                              <TableCell colSpan={9} className="text-center py-4">
                                Nenhum recurso encontrado
                              </TableCell>
                            </TableRow>
                          ) : (
                            filteredAndSortedResources.map(resource => (
                              <TableRow key={resource.uniquename}>
                                <TableCell className="font-medium">
                                  <div className="flex items-center space-x-2">
                                    <img 
                                      src={getItemIconUrl(resource.uniquename, 32)} 
                                      alt={resource.nicename} 
                                      className="h-8 w-8 rounded bg-gray-100 dark:bg-gray-800 object-contain"
                                      onError={(e) => {
                                        (e.target as HTMLImageElement).src = 'https://render.albiononline.com/v1/item/T4_BAG.png?size=32&quality=1';
                                      }}
                                    />
                                    <span>{resource.nicename}</span>
                                  </div>
                                </TableCell>
                                <TableCell>
                                  <Badge variant="outline">
                                    T{resource.tier}
                                    {resource.enchantment_level > 0 && `.${resource.enchantment_level}`}
                                  </Badge>
                                </TableCell>
                                {ALBION_CITIES.map(city => (
                                  renderPriceCell(resource.prices[city], city, resource)
                                ))}
                                <TableCell className="text-right">
                                  {resource.flipping ? (
                                    <div>
                                      <Badge 
                                        className={
                                          resource.flipping.profit_percentage >= 20 
                                            ? 'bg-green-100 text-green-800 hover:bg-green-100 dark:bg-green-900 dark:text-green-300' 
                                            : 'bg-blue-100 text-blue-800 hover:bg-blue-100 dark:bg-blue-900 dark:text-blue-300'
                                        }
                                      >
                                        {resource.flipping.profit_percentage.toFixed(2)}%
                                      </Badge>
                                      <div className="text-xs text-muted-foreground mt-1">
                                        {resource.flipping.buy_city} → {resource.flipping.sell_city}
                                      </div>
                                    </div>
                                  ) : (
                                    <span className="text-muted-foreground">-</span>
                                  )}
                                </TableCell>
                              </TableRow>
                            ))
                          )}
                        </TableBody>
                      </Table>
                    </div>
                  </CardContent>
                </Card>
              </div>
            </div>

            <Separator />

            <Card>
              <CardHeader>
                <CardTitle>Melhores Oportunidades de Flipping</CardTitle>
                <CardDescription>
                  Top 5 recursos com maior potencial de lucro entre cidades
                </CardDescription>
              </CardHeader>
              <CardContent>
                {bestFlippingOpportunities.length === 0 ? (
                  <div className="text-center py-4 text-muted-foreground">
                    Nenhuma oportunidade de flipping encontrada
                  </div>
                ) : (
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead className="w-[240px]">Recurso</TableHead>
                        <TableHead>Compra</TableHead>
                        <TableHead>Preço Compra</TableHead>
                        <TableHead>Venda</TableHead>
                        <TableHead>Preço Venda</TableHead>
                        <TableHead>Lucro</TableHead>
                        <TableHead className="text-right">Margem</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {bestFlippingOpportunities.map(resource => (
                        <TableRow key={`flip-${resource.uniquename}`}>
                          <TableCell className="font-medium">
                            <div className="flex items-center space-x-2">
                              <img 
                                src={getItemIconUrl(resource.uniquename, 32)} 
                                alt={resource.nicename} 
                                className="h-8 w-8 rounded bg-gray-100 dark:bg-gray-800 object-contain"
                                onError={(e) => {
                                  (e.target as HTMLImageElement).src = 'https://render.albiononline.com/v1/item/T4_BAG.png?size=32&quality=1';
                                }}
                              />
                              <div>
                                <div>{resource.nicename}</div>
                                <div className="text-xs text-muted-foreground">
                                  T{resource.tier}
                                  {resource.enchantment_level > 0 && `.${resource.enchantment_level}`}
                                </div>
                              </div>
                            </div>
                          </TableCell>
                          <TableCell>{resource.flipping?.buy_city}</TableCell>
                          <TableCell className="font-medium text-green-600 dark:text-green-400">
                            {formatPrice(resource.flipping?.buy_price || 0)}
                          </TableCell>
                          <TableCell>{resource.flipping?.sell_city}</TableCell>
                          <TableCell className="font-medium text-red-600 dark:text-red-400">
                            {formatPrice(resource.flipping?.sell_price || 0)}
                          </TableCell>
                          <TableCell className="font-medium">
                            {formatPrice(resource.flipping?.profit || 0)}
                          </TableCell>
                          <TableCell className="text-right">
                            <Badge 
                              className={
                                (resource.flipping?.profit_percentage || 0) >= 20 
                                  ? 'bg-green-100 text-green-800 hover:bg-green-100 dark:bg-green-900 dark:text-green-300' 
                                  : 'bg-blue-100 text-blue-800 hover:bg-blue-100 dark:bg-blue-900 dark:text-blue-300'
                              }
                            >
                              {resource.flipping?.profit_percentage.toFixed(2)}%
                            </Badge>
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                )}
              </CardContent>
            </Card>
          </>
        )}
      </div>
    </AlbionLayout>
  );
}
