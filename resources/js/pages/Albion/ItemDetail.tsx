import { useState, useEffect } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import {
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

// Interfaces para a nova estrutura de dados
interface City {
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

interface Quality {
  quality: number;
  cities: City[];
}

interface Material {
  uniquename: string;
  nicename: string;
  amount: number;
  max_return_amount: number;
  shopcategory?: string;
  shopsubcategory1?: string;
  slottype?: string;
  qualities: Quality[];
}

interface CraftingAnalysis {
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

interface AlbionItemData {
  id: number;
  uniquename: string;
  nicename: string;
  qualities: Quality[];
  materials: Material[];
  crafting_analysis: CraftingAnalysis[];
  crafting_requirements?: any;
  // Outros campos do modelo
  tier?: number;
  enchantment_level?: number;
  description?: string;
  shopcategory?: string;
  shopsubcategory1?: string;
  slottype?: string;
  [key: string]: any; // Para outros campos dinâmicos
}

// Componente principal
export default function ItemDetail({ item }: { item?: AlbionItemData }) {
  const { props } = usePage();
  const itemData = item || (props as any).item;
  
  const [selectedCity, setSelectedCity] = useState<string>('Bridgewatch');
  const [selectedQuality, setSelectedQuality] = useState<number>(1);
  const [profitMargin, setProfitMargin] = useState<number>(0);
  const [profitAmount, setProfitAmount] = useState<number>(0);
  const [loading, setLoading] = useState<boolean>(false);
  const [error, setError] = useState<string>('');

  useEffect(() => {
    if (!itemData) {
      setError('Item não encontrado. Verifique o ID do item.');
    }
  }, [itemData]);

  // Log para debug
  console.log('Item data:', itemData);
  
  // Calcular o custo total dos materiais
  const calculateTotalMaterialCost = (): number => {
    if (!itemData?.materials || itemData.materials.length === 0) return 0;

    let totalCost = 0;

    for (const material of itemData.materials) {
      // Buscar o preço do material na cidade selecionada e com a qualidade selecionada
      const materialQuality = material.qualities.find(q => q.quality === 1);
      if (!materialQuality) continue;
      const cityData = materialQuality.cities.find(c => c.city === selectedCity);
      if (!cityData) continue;
      console.log(cityData.sell_price_min)
      // Usar o preço de compra máximo como referência para o custo
      const materialPrice = cityData.sell_price_min || 0;
      totalCost += materialPrice * material.amount;
    }

    return totalCost;
  };

  // Obter o preço de venda mínimo do item na cidade selecionada
  const getMinSellPrice = (): number => {
    const quality = itemData?.qualities?.find(q => q.quality === selectedQuality);
    if (!quality) return 0;

    const cityData = quality.cities.find(c => c.city === selectedCity);
    return cityData?.sell_price_min || 0;
  };

  // Função para obter a classe CSS baseada na margem de lucro
  const getProfitClass = (): string => {
    if (profitMargin > 20) return "text-green-500";
    if (profitMargin > 0) return "text-yellow-500";
    return "text-red-500";
  };

  // Função para formatar a porcentagem
  const formatPercent = (value: number): string => {
    return `${value.toFixed(2)}%`;
  };

  // Função para obter a data mais recente de atualização de preço
  const getLatestPriceDate = (quality: Quality): string => {
    const cityData = quality.cities.find(c => c.city === selectedCity);

    if (cityData) {
      const dates = [
        cityData.sell_price_min_date,
        cityData.sell_price_max_date,
        cityData.buy_price_min_date,
        cityData.buy_price_max_date
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

  // Estado para armazenar valores calculados
  const [calculatedValues, setCalculatedValues] = useState({
    totalCost: 0,
    sellPrice: 0,
    lastUpdated: new Date()
  });

  // Forçar recalculação quando a cidade ou qualidade mudam
  useEffect(() => {
    try {
      console.log(`Cidade selecionada alterada para: ${selectedCity}`);
      console.log(`Qualidade selecionada alterada para: ${selectedQuality}`);

      // Obter o preço de venda mínimo do item na cidade selecionada
      const sellPrice = getMinSellPrice();

      // Calcular o custo total dos materiais
      const totalCost = calculateTotalMaterialCost();

      console.log(`Preço de venda: ${sellPrice}, Custo total: ${totalCost}`);

      // Atualizar valores calculados
      setCalculatedValues({
        totalCost,
        sellPrice,
        lastUpdated: new Date()
      });

      if (sellPrice > 0 && totalCost > 0) {
        // Calcular o lucro bruto
        const profit = sellPrice - totalCost;
        setProfitAmount(profit);

        // Calcular a margem de lucro como porcentagem
        const margin = (profit / totalCost) * 100;
        setProfitMargin(margin);

        console.log(`Lucro: ${profit}, Margem: ${margin}%`);
      } else {
        setProfitAmount(0);
        setProfitMargin(0);
      }
    } catch (error) {
      console.error('Erro ao calcular lucro:', error);
      setProfitAmount(0);
      setProfitMargin(0);
    }
  }, [selectedCity, selectedQuality]);

  // Se o item não for encontrado
  if (!itemData) {
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
      title={itemData.nicename || itemData.uniquename}
      description="Detalhes, preços e informações de crafting"
      customBreadcrumbs={[
        {
          title: 'Albion Online',
          href: '/albion',
        },
        {
          title: itemData.nicename || itemData.uniquename,
          href: `/albion/item/${itemData.uniquename}`
        }
      ]}
    >
      <div className="space-y-8">
        <div className="flex flex-col items-start gap-6 md:flex-row">
          <div className="flex h-32 w-32 items-center justify-center rounded-lg border border-border bg-muted p-2">
            <img
              src={getItemIconUrl(getBaseItemId(itemData.uniquename), 128)}
              alt={itemData.nicename || itemData.uniquename}
              className="h-full w-full object-contain"
              onError={(e) => {
                (e.target as HTMLImageElement).src = 'https://render.albiononline.com/v1/item/T4_BAG.png?size=128&quality=1';
              }}
            />
          </div>

          <div className="space-y-2">
            <h1 className="text-2xl font-bold">
              {itemData.nicename || itemData.uniquename}
              {getEnchantmentLevel(itemData.uniquename) > 0 && (
                <span className="ml-2 text-sm font-medium text-blue-500">
                  Encantamento Nível {getEnchantmentLevel(itemData.uniquename)}
                </span>
              )}
            </h1>
            <p className="text-sm text-muted-foreground">ID: {itemData.uniquename}</p>

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
                onClick={() => setSelectedCity(`${city}`)}
                variant={selectedCity === city ? "default" : "outline"}
                size="sm"
                className="w-full"
              >
                {city}
              </Button>
            ))}
          </div>
        </div>

        <div className="space-y-6">
          <HeadingSmall
            title="Selecione a Qualidade"
            description="Escolha a qualidade do item para análise"
          />

          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-5">
            {[1, 2, 3, 4, 5].map((quality) => {
              // Verificar se existem dados para esta qualidade
              const hasQualityData = itemData.qualities.some(q => q.quality === quality);

              return (
                <Button
                  key={quality}
                  onClick={() => setSelectedQuality(quality)}
                  variant={selectedQuality === quality ? "default" : "outline"}
                  size="sm"
                  className="w-full"
                  disabled={!hasQualityData}
                >
                  {getQualityName(quality)}
                </Button>
              );
            })}
          </div>
        </div>

        <Separator />

        {/* Seção de Análise de Crafting */}
        <div className="space-y-6">
          <HeadingSmall
            title="Análise de Crafting"
            description="Análise de custo e lucro potencial para craftar este item"
          />

          <div className="grid gap-6 md:grid-cols-2">
            <div className="rounded-lg border border-border p-6">
              <h3 className="mb-4 text-lg font-medium">Resumo de Crafting</h3>

              {itemData.materials && itemData.materials.length > 0 ? (
                <div className="space-y-4">
                  <div className="flex justify-between">
                    <span className="text-muted-foreground">Custo Total de Materiais:</span>
                    <span className="font-medium">{formatPrice(calculatedValues.totalCost)}</span>
                  </div>

                  <div className="flex justify-between">
                    <span className="text-muted-foreground">Preço de Venda (Mínimo):</span>
                    <span className="font-medium">{formatPrice(calculatedValues.sellPrice)}</span>
                  </div>

                  <div className="flex justify-between">
                    <span className="text-muted-foreground">Cidade:</span>
                    <span className="font-medium">{selectedCity}</span>
                  </div>

                  <div className="flex justify-between">
                    <span className="text-muted-foreground">Qualidade:</span>
                    <span className="font-medium">{getQualityName(selectedQuality)}</span>
                  </div>

                  {itemData.qualities.length > 0 && (
                    <div className="flex justify-between">
                      <span className="text-muted-foreground">Última Atualização:</span>
                      <div className="text-right text-xs text-muted-foreground">
                        {getLatestPriceDate(itemData.qualities.find(q => q.quality === selectedQuality) || itemData.qualities[0])}
                      </div>
                    </div>
                  )}

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
              ) : (
                <p className="text-muted-foreground">Este item não pode ser craftado ou não possui informações de crafting disponíveis.</p>
              )}
            </div>

            <div className="rounded-lg border border-border p-6">
              <h3 className="mb-4 text-lg font-medium">Materiais Necessários</h3>

              {!itemData.materials || itemData.materials.length === 0 ? (
                <p className="text-muted-foreground">Este item não pode ser craftado ou não possui informações de crafting disponíveis.</p>
              ) : (
                <div className="space-y-4">
                  {itemData.materials.map((material, index) => (
                    <div key={`${material.uniquename}-${index}`} className="flex items-center justify-between">
                      <div className="flex items-center">
                        <img
                          src={getItemIconUrl(getBaseItemId(material.uniquename), 40)}
                          alt={material.nicename}
                          className="mr-3 h-8 w-8 rounded object-contain"
                          onError={(e) => {
                            (e.target as HTMLImageElement).src = 'https://render.albiononline.com/v1/item/T4_BAG.png?size=40&quality=1';
                          }}
                        />
                        <div>
                          <div>
                            {material.nicename}
                            {getEnchantmentLevel(material.uniquename) > 0 && (
                              <span className="ml-1 text-xs font-medium text-blue-500">
                                (Encantamento Nível {getEnchantmentLevel(material.uniquename)})
                              </span>
                            )}
                          </div>
                          <div className="text-xs text-muted-foreground">{material.uniquename}</div>
                        </div>
                      </div>
                      <div className="flex items-center">
                        <span className="text-muted-foreground">{material.amount}x</span>
                        <span className="ml-2 text-sm font-medium">
                          {formatPrice(
                            (() => {
                              const quality = material.qualities.find(q => q.quality === 1);
                              if (!quality) return 0;

                              const cityData = quality.cities.find(c => c.city === selectedCity);
                              return cityData?.buy_price_max || 0;
                            })()
                          )}
                        </span>
                      </div>
                    </div>
                  ))}

                  <Separator />

                  <div className="flex justify-between">
                    <span className="font-medium">Total:</span>
                    <span className="font-medium">{formatPrice(calculatedValues.totalCost)}</span>
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>

        <Separator />

        {/* Seção de Análise de Lucratividade por Cidade */}
        {itemData.crafting_analysis && itemData.crafting_analysis.length > 0 && (
          <>
            <div className="space-y-6">
              <HeadingSmall
                title="Análise de Lucratividade por Cidade"
                description="Comparativo de lucratividade em diferentes cidades e qualidades"
              />

              <div className="rounded-md border border-border">
                <div className="overflow-x-auto">
                  <table className="w-full divide-y divide-border">
                    <thead>
                      <tr className="bg-muted">
                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">Cidade</th>
                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">Qualidade</th>
                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">Custo Materiais</th>
                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">Preço Venda</th>
                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">Lucro</th>
                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">Margem</th>
                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">Status</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-border bg-background">
                      {itemData.crafting_analysis
                        .filter(analysis => analysis.quality === selectedQuality)
                        .map((analysis, index) => {
                          // Determinar a classe CSS para o lucro
                          const profitClass = analysis.is_profitable 
                            ? analysis.profit_margin > 20 ? "text-green-500" : "text-yellow-500"
                            : "text-red-500";
                          
                          // Formatar a data
                          const formattedDate = analysis.updated_at
                            ? new Date(analysis.updated_at).toLocaleString('pt-BR', {
                                day: '2-digit',
                                month: '2-digit',
                                year: 'numeric'
                              })
                            : '-';
                          
                          return (
                            <tr key={`${analysis.city}-${index}`} className="hover:bg-muted/50">
                              <td className="whitespace-nowrap px-6 py-4 text-sm text-muted-foreground">{analysis.city}</td>
                              <td className="whitespace-nowrap px-6 py-4 text-sm text-muted-foreground">{getQualityName(analysis.quality)}</td>
                              <td className="whitespace-nowrap px-6 py-4 text-sm text-muted-foreground">{formatPrice(analysis.material_cost)}</td>
                              <td className="whitespace-nowrap px-6 py-4 text-sm text-muted-foreground">{formatPrice(analysis.sell_price)}</td>
                              <td className={`whitespace-nowrap px-6 py-4 text-sm font-medium ${profitClass}`}>{formatPrice(analysis.profit)}</td>
                              <td className={`whitespace-nowrap px-6 py-4 text-sm font-medium ${profitClass}`}>{analysis.profit_margin.toFixed(2)}%</td>
                              <td className="whitespace-nowrap px-6 py-4 text-sm">
                                <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${analysis.is_profitable ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                                  {analysis.is_profitable ? 'Lucrativo' : 'Prejuízo'}
                                </span>
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
          </>
        )}

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
                  {itemData.qualities
                    .filter(quality => quality.quality === selectedQuality)
                    .flatMap(quality =>
                      quality.cities.map((cityData, index) => {
                        // Formatar a data mais recente
                        const dates = [
                          cityData.sell_price_min_date,
                          cityData.sell_price_max_date,
                          cityData.buy_price_min_date,
                          cityData.buy_price_max_date
                        ].filter(Boolean);

                        // Ordenar datas do mais recente para o mais antigo
                        const sortedDates = dates.sort((a, b) =>
                          new Date(b || '').getTime() - new Date(a || '').getTime()
                        );

                        const mostRecentDate = sortedDates.length > 0 ? sortedDates[0] : null;
                        const formattedDate = mostRecentDate && mostRecentDate !== "0001-01-01T00:00:00"
                          ? new Date(mostRecentDate).toLocaleString('pt-BR', {
                            day: '2-digit',
                            month: '2-digit',
                            year: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                          })
                          : '-';

                        return (
                          <tr key={`${cityData.city}-${index}`} className="hover:bg-muted/50">
                            <td className="whitespace-nowrap px-6 py-4 text-sm text-muted-foreground">{cityData.city}</td>
                            <td className="whitespace-nowrap px-6 py-4 text-sm text-muted-foreground">{getQualityName(quality.quality)}</td>
                            <td className="whitespace-nowrap px-6 py-4 text-sm text-muted-foreground">
                              {cityData.sell_price_min === cityData.sell_price_max
                                ? formatPrice(cityData.sell_price_min)
                                : `${formatPrice(cityData.sell_price_min)} ~ ${formatPrice(cityData.sell_price_max)}`}
                            </td>
                            <td className="whitespace-nowrap px-6 py-4 text-sm text-muted-foreground">
                              {cityData.buy_price_min === cityData.buy_price_max
                                ? formatPrice(cityData.buy_price_min)
                                : `${formatPrice(cityData.buy_price_min)} ~ ${formatPrice(cityData.buy_price_max)}`}
                            </td>
                            <td className="whitespace-nowrap px-6 py-4 text-sm text-muted-foreground">
                              {formattedDate}
                            </td>
                          </tr>
                        );
                      })
                    )}
                </tbody>
              </table>
            </div>
          </div>
        </div>


      </div>
    </AlbionLayout>
  );
}
