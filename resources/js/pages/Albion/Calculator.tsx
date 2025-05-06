import { useState } from 'react';
import AlbionLayout from '@/layouts/albion/layout';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import HeadingSmall from '@/components/heading-small';
import AlbionItemSelector from '@/components/AlbionItemSelector';
import { 
  AlbionItem, 
  ItemPrice, 
  fetchItemPrices, 
  formatPrice, 
  getItemIconUrl,
  ALBION_CITIES
} from '@/utils/albionUtils';
import axios from 'axios';

export default function AlbionCalculator() {
  const [selectedItem, setSelectedItem] = useState<AlbionItem | null>(null);
  const [selectedCity, setSelectedCity] = useState<string>('Caerleon');
  const [loading, setLoading] = useState(false);
  const [calculationResult, setCalculationResult] = useState<{
    materialCost: number;
    marketPrice: number;
    profit: number;
    profitMargin: number;
    materials: any[];
  } | null>(null);

  // Selecionar um item
  const selectItem = (item: AlbionItem) => {
    setSelectedItem(item);
    setCalculationResult(null);
  };

  // Calcular lucro
  const calculateProfit = async () => {
    if (!selectedItem) return;

    setLoading(true);
    try {
      // Buscar informações de crafting
      const craftingResponse = await axios.get(`/api/albion/crafting/${selectedItem.uniqueName}`);
      const craftingInfo = craftingResponse.data;

      // Buscar preço de mercado
      const priceData = await fetchItemPrices([selectedItem.uniqueName], [selectedCity]);
      const itemPrice = priceData.find(p => p.item_id === selectedItem.uniqueName && p.city === selectedCity);
      
      if (craftingInfo && itemPrice) {
        const marketPrice = itemPrice.sell_price_min || 0;
        const materialCost = craftingInfo.totalCost;
        const profit = marketPrice - materialCost;
        const profitMargin = materialCost > 0 ? (profit / materialCost) * 100 : 0;

        setCalculationResult({
          materialCost,
          marketPrice,
          profit,
          profitMargin,
          materials: craftingInfo.materials
        });
      }
    } catch (error) {
      console.error('Erro ao calcular lucro:', error);
    } finally {
      setLoading(false);
    }
  };

  // Obter classe CSS baseada na margem de lucro
  const getProfitClass = () => {
    if (!calculationResult) return '';
    if (calculationResult.profitMargin > 20) return "text-green-500";
    if (calculationResult.profitMargin > 0) return "text-yellow-500";
    return "text-red-500";
  };

  // Formatar porcentagem
  const formatPercent = (value: number) => {
    return `${value.toFixed(2)}%`;
  };

  return (
    <AlbionLayout
      title="Calculadora de Lucro"
      description="Calcule o lucro potencial de crafting de itens no Albion Online"
    >
      <div className="space-y-8">
        <div className="space-y-6">
          <HeadingSmall 
            title="Selecione um Item" 
            description="Escolha o item que deseja calcular o lucro de crafting" 
          />
          
          <AlbionItemSelector onItemSelect={selectItem} />
          
          {selectedItem && (
            <div className="flex items-center rounded-md border border-border bg-card p-4">
              <img 
                src={getItemIconUrl(selectedItem.uniqueName, 64)} 
                alt={selectedItem.localizedNames['PT-BR'] || selectedItem.uniqueName} 
                className="mr-4 h-16 w-16 rounded object-contain"
                onError={(e) => {
                  (e.target as HTMLImageElement).src = 'https://render.albiononline.com/v1/item/T4_BAG.png?size=64&quality=1';
                }}
              />
              <div>
                <h3 className="text-lg font-medium">{selectedItem.localizedNames['PT-BR'] || selectedItem.uniqueName}</h3>
                {selectedItem.localizedNames['EN-US'] && selectedItem.localizedNames['PT-BR'] !== selectedItem.localizedNames['EN-US'] && (
                  <p className="text-sm text-muted-foreground">{selectedItem.localizedNames['EN-US']}</p>
                )}
              </div>
            </div>
          )}
        </div>

        <Separator />

        <div className="space-y-6">
          <HeadingSmall 
            title="Selecione a Cidade" 
            description="Escolha a cidade para calcular o lucro baseado nos preços locais" 
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

        <div className="flex justify-center">
          <Button
            onClick={calculateProfit}
            disabled={loading || !selectedItem}
            size="lg"
            className="min-w-[200px]"
          >
            {loading ? (
              <>
                <svg className="mr-2 h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                  <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Calculando...
              </>
            ) : (
              'Calcular Lucro'
            )}
          </Button>
        </div>

        {calculationResult && (
          <>
            <Separator />
            
            <div className="space-y-6">
              <HeadingSmall 
                title="Resultado da Análise" 
                description="Análise de custo e lucro potencial para o item selecionado" 
              />
              
              <div className="grid gap-6 md:grid-cols-2">
                <div className="rounded-lg border border-border p-6">
                  <h3 className="mb-4 text-lg font-medium">Análise de Custo</h3>
                  
                  <div className="space-y-4">
                    <div className="flex justify-between">
                      <span className="text-muted-foreground">Custo Total de Materiais:</span>
                      <span className="font-medium">{formatPrice(calculationResult.materialCost)}</span>
                    </div>
                    
                    <div className="flex justify-between">
                      <span className="text-muted-foreground">Preço de Venda ({selectedCity}):</span>
                      <span className="font-medium">{formatPrice(calculationResult.marketPrice)}</span>
                    </div>
                    
                    <Separator />
                    
                    <div className="flex justify-between">
                      <span className="text-muted-foreground">Lucro Potencial:</span>
                      <span className={`font-medium ${getProfitClass()}`}>
                        {formatPrice(calculationResult.profit)} ({formatPercent(calculationResult.profitMargin)})
                      </span>
                    </div>
                    
                    <div className="mt-4 rounded-md bg-muted p-3 text-sm text-muted-foreground">
                      <p>
                        {calculationResult.profitMargin > 20 
                          ? "✅ Excelente oportunidade de lucro!" 
                          : calculationResult.profitMargin > 0 
                            ? "⚠️ Lucro marginal, considere os custos de taxa do mercado." 
                            : "❌ Prejuízo! Não é recomendado craftar este item para venda."}
                      </p>
                    </div>
                  </div>
                </div>
                
                <div className="rounded-lg border border-border p-6">
                  <h3 className="mb-4 text-lg font-medium">Materiais Necessários</h3>
                  
                  {calculationResult.materials.length === 0 ? (
                    <p className="text-muted-foreground">Este item não pode ser craftado ou não possui informações de crafting disponíveis.</p>
                  ) : (
                    <div className="space-y-4">
                      {calculationResult.materials.map((material: any, index: number) => (
                        <div key={`${material.itemId}-${index}`} className="flex items-center justify-between">
                          <div className="flex items-center">
                            <img 
                              src={getItemIconUrl(material.itemId, 40)} 
                              alt={material.name} 
                              className="mr-3 h-8 w-8 rounded object-contain"
                              onError={(e) => {
                                (e.target as HTMLImageElement).src = 'https://render.albiononline.com/v1/item/T4_BAG.png?size=40&quality=1';
                              }}
                            />
                            <div>
                              <div>{material.name}</div>
                              <div className="text-xs text-muted-foreground">{material.itemId}</div>
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
                        <span className="font-medium">{formatPrice(calculationResult.materialCost)}</span>
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
