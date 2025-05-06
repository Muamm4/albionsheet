import { useState, useEffect } from 'react';
import { Link, router } from '@inertiajs/react';
import axios from 'axios';
import { 
  AlbionItem, 
  CraftingInfo, 
  ItemPrice, 
  fetchItemPrices, 
  formatPrice, 
  getQualityName, 
  ALBION_CITIES,
  getItemIconUrl,
  getBaseItemId,
  getEnchantmentLevel
} from '@/utils/albionUtils';
import AlbionLayout from '@/layouts/albion/layout';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import HeadingSmall from '@/components/heading-small';

// Interface para itens craftáveis
interface CraftableItem {
  id: string;
  name: string;
  materials: Array<{
    itemId: string;
    name: string;
    quantity: number;
    price: number;
  }>;
  craftingInfo: CraftingInfo;
}

// Componente principal
export default function ItemDetail({ itemId }: { itemId: string }) {
  const [item, setItem] = useState<AlbionItem | null>(null);
  const [prices, setPrices] = useState<ItemPrice[]>([]);
  const [craftingInfo, setCraftingInfo] = useState<CraftingInfo | null>(null);
  const [craftableItems, setCraftableItems] = useState<CraftableItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [selectedCity, setSelectedCity] = useState<string>('Bridgewatch');
  const [profitMargin, setProfitMargin] = useState<number>(0);
  const [profitAmount, setProfitAmount] = useState<number>(0);
  const [priceLoadError, setPriceLoadError] = useState<boolean>(false);

  // Buscar dados do item
  useEffect(() => {
    const fetchData = async () => {
      try {
        setLoading(true);
        setPriceLoadError(false);
        
        // Buscar informações do item
        const itemResponse = await axios.get(`/api/albion/item/${itemId}`);
        setItem(itemResponse.data);

        try {
          // Buscar preços do item para todas as cidades de uma vez
          const priceData = await fetchItemPrices([itemId], ALBION_CITIES);
          setPrices(priceData);
          
          if (priceData.length === 0) {
            console.warn('Nenhum dado de preço retornado para o item:', itemId);
            setPriceLoadError(true);
          }
        } catch (priceError) {
          console.error('Erro ao buscar preços:', priceError);
          setPriceLoadError(true);
        }

        // Buscar informações de crafting
        const craftingResponse = await axios.get(`/api/albion/crafting/${itemId}`);
        setCraftingInfo(craftingResponse.data);
        
        // Buscar itens que podem ser craftados com este item
        try {
          const craftableResponse = await axios.get(`/api/albion/craftable/${itemId}`);
          if (craftableResponse.data && Array.isArray(craftableResponse.data)) {
            setCraftableItems(craftableResponse.data);
            
            // Buscar preços para todos os itens craftáveis de uma vez
            if (craftableResponse.data.length > 0) {
              // Coletar todos os IDs de materiais necessários para os itens craftáveis
              const allMaterialIds = new Set<string>();
              craftableResponse.data.forEach(item => {
                // Adicionar o ID do item craftável
                allMaterialIds.add(item.id);
                
                // Adicionar os IDs de todos os materiais
                if (item.materials && Array.isArray(item.materials)) {
                  item.materials.forEach(material => {
                    if (material.itemId) {
                      allMaterialIds.add(material.itemId);
                    }
                  });
                }
              });
              
              // Converter o Set para array e remover o itemId atual (já temos os preços)
              const materialIdsToFetch = Array.from(allMaterialIds).filter(id => id !== itemId);
              
              if (materialIdsToFetch.length > 0) {
                try {
                  const materialPrices = await fetchItemPrices(materialIdsToFetch, ALBION_CITIES);
                  // Adicionar esses preços ao estado de preços
                  setPrices(prevPrices => [...prevPrices, ...materialPrices]);
                } catch (materialPriceError) {
                  console.error('Erro ao buscar preços dos materiais:', materialPriceError);
                }
              }
            }
          } else {
            console.warn('Resposta inválida para itens craftáveis:', craftableResponse.data);
            setCraftableItems([]);
          }
        } catch (craftError) {
          console.error('Erro ao buscar itens craftáveis:', craftError);
          setCraftableItems([]);
        }

        setLoading(false);
      } catch (error) {
        console.error('Erro ao buscar dados do item:', error);
        setLoading(false);
      }
    };

    fetchData();
  }, [itemId]);

  // Calcular margem de lucro quando os dados estiverem disponíveis
  useEffect(() => {
    if (prices.length > 0 && craftingInfo) {
      const cityPrices = prices.filter(price => price.city === selectedCity && price.item_id === itemId);
      
      if (cityPrices.length > 0) {
        const sellPrice = cityPrices[0].sell_price_min || 0;
        
        if (sellPrice > 0 && craftingInfo.totalCost > 0) {
          const profit = sellPrice - craftingInfo.totalCost;
          setProfitAmount(profit);
          setProfitMargin((profit / craftingInfo.totalCost) * 100);
        } else {
          setProfitAmount(0);
          setProfitMargin(0);
        }
      }
    }
  }, [prices, craftingInfo, selectedCity, itemId]);

  // Função para obter o preço de venda mínimo na cidade selecionada
  const getMinSellPrice = (itemId: string) => {
    const cityPrices = prices.filter(price => price.city === selectedCity && price.item_id === itemId);
    return cityPrices.length > 0 ? cityPrices[0].sell_price_min : 0;
  };

  // Função para obter a classe CSS baseada na margem de lucro
  const getProfitClass = () => {
    if (profitMargin > 20) return "text-green-500";
    if (profitMargin > 0) return "text-yellow-500";
    return "text-red-500";
  };

  // Função para formatar a porcentagem
  const formatPercent = (value: number) => {
    return `${value.toFixed(2)}%`;
  };

  // Função para obter a data mais recente de atualização de preço
  const getLatestPriceDate = (itemId: string, city: string) => {
    const cityPrices = prices.filter(price => price.city === city && price.item_id === itemId);
    
    if (cityPrices.length > 0) {
      const price = cityPrices[0];
      const dates = [
        price.sell_price_min_date,
        price.sell_price_max_date,
        price.buy_price_min_date,
        price.buy_price_max_date
      ].filter(Boolean);
      
      // Ordenar datas do mais recente para o mais antigo
      const sortedDates = dates.sort((a, b) => 
        new Date(b || '').getTime() - new Date(a || '').getTime()
      );
      
      const mostRecentDate = sortedDates.length > 0 ? sortedDates[0] : null;
      
      if (mostRecentDate) {
        return `Atualizado em ${new Date(mostRecentDate).toLocaleString('pt-BR', {
          day: '2-digit',
          month: '2-digit',
          year: 'numeric',
          hour: '2-digit',
          minute: '2-digit'
        })}`;
      }
    }
    
    return 'Data não disponível';
  };

  // Se estiver carregando, mostrar indicador de carregamento
  if (loading) {
    return (
      <AlbionLayout
        title="Detalhes do Item"
        description="Carregando informações do item..."
      >
        <div className="flex h-64 items-center justify-center">
          <div className="flex flex-col items-center">
            <svg className="h-12 w-12 animate-spin text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
              <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p className="mt-4 text-lg font-medium">Carregando informações do item...</p>
          </div>
        </div>
      </AlbionLayout>
    );
  }

  // Se o item não for encontrado
  if (!item) {
    return (
      <AlbionLayout
        title="Item não encontrado"
        description="O item solicitado não foi encontrado"
      >
        <div className="flex h-64 flex-col items-center justify-center space-y-4">
          <p className="text-lg text-muted-foreground">Item não encontrado ou indisponível.</p>
          <Button asChild>
            <Link href="/albion">Voltar para Consulta de Preços</Link>
          </Button>
        </div>
      </AlbionLayout>
    );
  }

  return (
    <AlbionLayout
      title={item?.localizedNames['PT-BR'] || item?.localizedNames['EN-US'] || itemId}
      description="Detalhes, preços e informações de crafting"
      customBreadcrumbs={[
        {
          title: 'Albion Online',
          href: '/albion',
        },
        {
          title: item?.localizedNames['PT-BR'] || item?.localizedNames['EN-US'] || itemId,
          href: `/albion/item/${itemId}`
        }
      ]}
    >
      <div className="space-y-8">
        <div className="flex flex-col items-start gap-6 md:flex-row">
          <div className="flex h-32 w-32 items-center justify-center rounded-lg border border-border bg-muted p-2">
            <img 
              src={getItemIconUrl(getBaseItemId(itemId), 128)} 
              alt={item?.localizedNames['PT-BR'] || itemId} 
              className="h-full w-full object-contain"
              onError={(e) => {
                (e.target as HTMLImageElement).src = 'https://render.albiononline.com/v1/item/T4_BAG.png?size=128&quality=1';
              }}
            />
          </div>
          
          <div className="space-y-2">
            <h1 className="text-2xl font-bold">
              {item?.localizedNames['PT-BR'] || getBaseItemId(itemId)}
              {getEnchantmentLevel(itemId) > 0 && (
                <span className="ml-2 text-sm font-medium text-blue-500">
                  Encantamento Nível {getEnchantmentLevel(itemId)}
                </span>
              )}
            </h1>
            {item?.localizedNames['EN-US'] && item?.localizedNames['PT-BR'] !== item?.localizedNames['EN-US'] && (
              <p className="text-muted-foreground">{item.localizedNames['EN-US']}</p>
            )}
            <p className="text-sm text-muted-foreground">ID: {itemId}</p>
            
            <div className="mt-4 flex flex-wrap gap-2">
              <Button
                onClick={() => router.visit('/albion')}
                variant="outline"
                size="sm"
              >
                Voltar para Consulta de Preços
              </Button>
            </div>
          </div>
          
        </div>

        <Separator />

        <div className="space-y-6">
          <HeadingSmall 
            title="Selecione a Cidade" 
            description="Escolha a cidade onde deseja verificar os preços" 
          />
          
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-6">
            {ALBION_CITIES.map((city) => (
              <Button
                key={city}
                onClick={() => setSelectedCity(city)}
                variant={selectedCity === city ? "default" : "outline"}
                size="sm"
                className="w-full"
              >
                {city}
              </Button>
            ))}
          </div>
        </div>

        <Separator />

        <div className="space-y-6">
          <HeadingSmall 
            title="Preços por Cidade" 
          />
          
          <div className="rounded-md border border-border">
            <div className="overflow-x-auto">
              <table className="w-full divide-y divide-border">
                <thead>
                  <tr className="bg-muted">
                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">Cidade</th>
                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">Qualidade</th>
                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">Preço Venda</th>
                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">Preço Compra</th>
                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">Atualizado</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border bg-background">
                  {prices
                    .filter(price => price.city === selectedCity && price.item_id === itemId)
                    .map((price, index) => {
                      // Formatar a data mais recente
                      const dates = [
                        price.sell_price_min_date,
                        price.sell_price_max_date,
                        price.buy_price_min_date,
                        price.buy_price_max_date
                      ].filter(Boolean);
                      
                      // Ordenar datas do mais recente para o mais antigo
                      const sortedDates = dates.sort((a, b) => 
                        new Date(b || '').getTime() - new Date(a || '').getTime()
                      );
                      
                      const mostRecentDate = sortedDates.length > 0 ? sortedDates[0] : null;
                      const formattedDate = mostRecentDate 
                        ? new Date(mostRecentDate).toLocaleString('pt-BR', {
                            day: '2-digit',
                            month: '2-digit',
                            year: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                          })
                        : 'N/A';
                      
                      return (
                        <tr key={`${price.item_id}-${price.city}-${price.quality}-${index}`} className="hover:bg-muted/50">
                          <td className="whitespace-nowrap px-6 py-4 text-sm text-muted-foreground">{price.city}</td>
                          <td className="whitespace-nowrap px-6 py-4 text-sm text-muted-foreground">{getQualityName(price.quality)}</td>
                            <td className="whitespace-nowrap px-6 py-4 text-sm text-muted-foreground">
                              {price.sell_price_min === price.sell_price_max
                                ? formatPrice(price.sell_price_min)
                                : `${formatPrice(price.sell_price_min)} ~ ${formatPrice(price.sell_price_max)}`}
                            </td>
                            <td className="whitespace-nowrap px-6 py-4 text-sm text-muted-foreground">
                              {price.buy_price_min === price.buy_price_max
                                ? formatPrice(price.buy_price_min)
                                : `${formatPrice(price.buy_price_min)} ~ ${formatPrice(price.buy_price_max)}`}
                            </td>
                            <td className="whitespace-nowrap px-6 py-4 text-sm text-muted-foreground">
                              {formattedDate}
                            </td>
                        </tr>
                      );
                    })}
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <Separator />

        <div className="space-y-6">
          <HeadingSmall 
            title="Item para Craftar" 
            description="Itens que podem ser craftados usando este material" 
          />
          
          {loading ? (
            <div className="rounded-md bg-muted p-4 text-muted-foreground">
              <p>Carregando itens craftáveis...</p>
            </div>
          ) : craftableItems.length === 0 ? (
            <div className="rounded-md bg-muted p-4 text-muted-foreground">
              <p>Este item não é usado como material para craftar outros itens.</p>
            </div>
          ) : (
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
              {craftableItems.map((craftableItem) => {
                // Calcular o custo total dos materiais
                const materialCost = craftableItem.materials.reduce((total, material) => {
                  // Buscar o preço do material na cidade selecionada
                  const materialPrice = prices.find(
                    p => p.item_id === material.itemId && p.city === selectedCity && p.quality === 1
                  );
                  
                  // Usar o preço de venda mínimo, ou o preço de compra mínimo como fallback
                  const price = materialPrice?.sell_price_min || 
                               materialPrice?.buy_price_min || 
                               0;
                               
                  // Exibir no console para debug
                  if (price === 0) {
                    console.log(`Preço zero para material: ${material.itemId} em ${selectedCity}`, 
                      { material, materialPrice });
                  }
                  
                  return total + (price * material.quantity);
                }, 0);
                
                // Buscar o preço de venda do item craftável
                const itemPrice = prices.find(
                  p => p.item_id === craftableItem.id && p.city === selectedCity && p.quality === 1
                );
                
                const sellPrice = itemPrice?.sell_price_min || 0;
                const profit = sellPrice - materialCost;
                const profitMargin = materialCost > 0 ? (profit / materialCost) * 100 : 0;
                
                // Determinar a classe CSS com base na margem de lucro
                let profitClass = "text-red-500";
                if (profitMargin > 20) profitClass = "text-green-500";
                else if (profitMargin > 0) profitClass = "text-yellow-500";
                
                return (
                  <div key={craftableItem.id} className="rounded-lg border border-border p-4 hover:bg-muted/50">
                    <div className="flex items-start gap-3">
                      <img 
                        src={getItemIconUrl(getBaseItemId(craftableItem.id), 64)} 
                        alt={craftableItem.name} 
                        className="h-12 w-12 rounded object-contain"
                        onError={(e) => {
                          (e.target as HTMLImageElement).src = 'https://render.albiononline.com/v1/item/T4_BAG.png?size=64&quality=1';
                        }}
                      />
                      <div>
                        <h3 className="font-medium">
                          {craftableItem.name || getBaseItemId(craftableItem.id)}
                          {getEnchantmentLevel(craftableItem.id) > 0 && (
                            <span className="ml-1 text-xs font-medium text-blue-500">
                              (Encantamento Nível {getEnchantmentLevel(craftableItem.id)})
                            </span>
                          )}
                        </h3>
                        
                        <div className="mt-2 space-y-1 text-sm">
                          <div className="flex justify-between">
                            <span className="text-muted-foreground">Custo:</span>
                            <span>{formatPrice(materialCost)}</span>
                          </div>
                          <div className="flex justify-between">
                            <span className="text-muted-foreground">Venda:</span>
                            <span>{formatPrice(sellPrice)}</span>
                          </div>
                          <div className="flex justify-between">
                            <span className="text-muted-foreground">Lucro:</span>
                            <span className={profitClass}>
                              {formatPrice(profit)} ({profitMargin.toFixed(2)}%)
                            </span>
                          </div>
                        </div>
                        
                        <div className="mt-3">
                          <Button
                            asChild
                            variant="outline"
                            size="sm"
                            className="w-full"
                          >
                            <Link href={`/albion/item/${craftableItem.id}`}>
                              Ver Detalhes
                            </Link>
                          </Button>
                        </div>
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>
        
        {craftingInfo && (
          <>
            <Separator />
            
            <div className="space-y-6">
              <HeadingSmall 
                title="Informações de Crafting" 
                description="Materiais necessários e análise de custo" 
              />
              
              <div className="grid gap-6 md:grid-cols-2">
                <div className="rounded-lg border border-border p-6">
                  <h3 className="mb-4 text-lg font-medium">Informações de Crafting</h3>
                  
                  {!craftingInfo ? (
                    <p className="text-muted-foreground">Carregando informações de crafting...</p>
                  ) : (
                    <div className="space-y-4">
                      <div className="flex justify-between">
                        <span className="text-muted-foreground">Custo Total de Materiais:</span>
                        <span className="font-medium">{formatPrice(craftingInfo.totalCost)}</span>
                      </div>
                      
                      <div className="flex justify-between">
                        <span className="text-muted-foreground">Preço de Venda ({selectedCity}):</span>
                        <div className="text-right">
                          <div className="font-medium">{formatPrice(getMinSellPrice(itemId))}</div>
                          {prices.length > 0 && (
                            <div className="text-xs text-muted-foreground">
                              {getLatestPriceDate(itemId, selectedCity)}
                            </div>
                          )}
                        </div>
                      </div>
                      
                      <Separator />
                      
                      <div className="flex justify-between">
                        <span className="text-muted-foreground">Lucro Potencial:</span>
                        <span className={`font-medium ${getProfitClass()}`}>
                          {formatPrice(profitAmount)} ({formatPercent(profitMargin)})
                        </span>
                      </div>
                      
                      <div className="mt-4 rounded-md bg-muted p-3 text-sm text-muted-foreground">
                        <p>
                          {profitMargin > 20 
                            ? "✅ Excelente oportunidade de lucro!" 
                            : profitMargin > 0 
                              ? "⚠️ Lucro marginal, considere os custos de taxa do mercado." 
                              : "❌ Prejuízo! Não é recomendado craftar este item para venda."}
                        </p>
                      </div>
                    </div>
                  )}
                </div>
                
                <div className="rounded-lg border border-border p-6">
                  <h3 className="mb-4 text-lg font-medium">Materiais Necessários</h3>
                  
                  {!craftingInfo || craftingInfo.materials.length === 0 ? (
                    <p className="text-muted-foreground">Este item não pode ser craftado ou não possui informações de crafting disponíveis.</p>
                  ) : (
                    <div className="space-y-4">
                      {craftingInfo.materials.map((material, index) => (
                        <div key={`${material.itemId}-${index}`} className="flex items-center justify-between">
                          <div className="flex items-center">
                            <img 
                              src={getItemIconUrl(getBaseItemId(material.itemId), 40)} 
                              alt={material.name} 
                              className="mr-3 h-8 w-8 rounded object-contain"
                              onError={(e) => {
                                (e.target as HTMLImageElement).src = 'https://render.albiononline.com/v1/item/T4_BAG.png?size=40&quality=1';
                              }}
                            />
                            <div>
                              <div>
                                {material.name}
                                {getEnchantmentLevel(material.itemId) > 0 && (
                                  <span className="ml-1 text-xs font-medium text-blue-500">
                                    (Encantamento Nível {getEnchantmentLevel(material.itemId)})
                                  </span>
                                )}
                              </div>
                              <div className="text-xs text-muted-foreground">{getBaseItemId(material.itemId)}</div>
                            </div>
                          </div>
                          <div className="flex items-center">
                            <span className="text-muted-foreground">{material.quantity}x</span>
                            <span className="ml-2 text-sm font-medium">{formatPrice(material.price)}</span>
                          </div>
                        </div>
                      ))}
                      
                      <Separator />
                      
                      <div className="flex justify-between">
                        <span className="font-medium">Total:</span>
                        <span className="font-medium">{formatPrice(craftingInfo.totalCost)}</span>
                      </div>
                    </div>
                  )}
                </div>
              </div>
            </div>
          </>
        )}
      </div>
    </AlbionLayout>
  );
}
