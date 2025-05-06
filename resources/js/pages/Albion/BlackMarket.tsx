import { useState } from 'react';
import { Link } from '@inertiajs/react';
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
  getItemIconUrl 
} from '@/utils/albionUtils';

export default function BlackMarket() {
  const [selectedItems, setSelectedItems] = useState<AlbionItem[]>([]);
  const [prices, setPrices] = useState<ItemPrice[]>([]);
  const [loading, setLoading] = useState(false);
  const [blackMarketPrices, setBlackMarketPrices] = useState<ItemPrice[]>([]);
  const [cityPrices, setCityPrices] = useState<ItemPrice[]>([]);
  const [selectedCity, setSelectedCity] = useState<string>('Caerleon');

  // Adicionar um item à lista de selecionados
  const addItem = (item: AlbionItem) => {
    if (!selectedItems.some(selected => selected.uniqueName === item.uniqueName)) {
      setSelectedItems([...selectedItems, item]);
    }
  };

  // Remover um item selecionado
  const removeItem = (uniqueName: string) => {
    setSelectedItems(selectedItems.filter(item => item.uniqueName !== uniqueName));
  };

  // Buscar os preços dos itens selecionados
  const getPrices = async () => {
    if (selectedItems.length === 0) {
      alert('Selecione pelo menos um item para consultar os preços.');
      return;
    }

    setLoading(true);
    try {
      // Buscar preços no Black Market
      const blackMarketData = await fetchItemPrices(
        selectedItems.map(item => item.uniqueName),
        ['Black Market']
      );
      
      // Buscar preços na cidade selecionada
      const cityData = await fetchItemPrices(
        selectedItems.map(item => item.uniqueName),
        [selectedCity]
      );
      
      setBlackMarketPrices(blackMarketData);
      setCityPrices(cityData);
      
      // Combinar os resultados
      setPrices([...blackMarketData, ...cityData]);
    } catch (error) {
      console.error('Erro ao buscar preços:', error);
      alert('Erro ao buscar preços. Tente novamente mais tarde.');
    } finally {
      setLoading(false);
    }
  };

  // Função para obter o nome do item
  const getItemName = (itemId: string) => {
    const item = selectedItems.find(item => item.uniqueName === itemId);
    return item?.localizedNames['PT-BR'] || item?.localizedNames['EN-US'] || item?.uniqueName || itemId;
  };

  // Função para obter o nome em inglês do item
  const getItemEnglishName = (itemId: string) => {
    const item = selectedItems.find(item => item.uniqueName === itemId);
    return item?.localizedNames['EN-US'] || '';
  };

  // Calcular a diferença de preço entre Black Market e cidade selecionada
  const calculatePriceDifference = (itemId: string) => {
    const blackMarketPrice = blackMarketPrices.find(
      price => price.item_id === itemId && price.city === 'Black Market'
    )?.sell_price_min || 0;
    
    const cityPrice = cityPrices.find(
      price => price.item_id === itemId && price.city === selectedCity
    )?.sell_price_min || 0;
    
    return {
      difference: blackMarketPrice - cityPrice,
      percentDifference: cityPrice > 0 ? ((blackMarketPrice - cityPrice) / cityPrice) * 100 : 0
    };
  };

  // Obter classe CSS baseada na diferença de preço
  const getPriceDifferenceClass = (difference: number) => {
    if (difference > 0) return "text-green-500";
    if (difference < 0) return "text-red-500";
    return "text-muted-foreground";
  };

  return (
    <AlbionLayout
      title="Mercado Black"
      description="Compare preços entre o Black Market e as cidades regulares"
    >
      <div className="space-y-8">
        <div className="space-y-6">
          <HeadingSmall 
            title="Buscar Item" 
            description="Pesquise e selecione os itens que deseja comparar os preços" 
          />
          
          <AlbionItemSelector onItemSelect={addItem} />
        </div>

        <Separator />

        <div className="space-y-6">
          <HeadingSmall 
            title="Itens Selecionados" 
            description="Itens que você selecionou para comparar os preços" 
          />
          
          {selectedItems.length === 0 ? (
            <div className="rounded-md bg-muted p-4 text-center text-sm text-muted-foreground">
              Nenhum item selecionado. Use a busca acima para adicionar itens.
            </div>
          ) : (
            <div className="flex flex-wrap gap-2">
              {selectedItems.map((item) => (
                <div
                  key={item.uniqueName}
                  className="flex items-center rounded-md border border-border bg-background px-3 py-2"
                >
                  <img 
                    src={getItemIconUrl(item.uniqueName, 40)} 
                    alt={item.localizedNames['PT-BR'] || item.uniqueName} 
                    className="mr-2 h-8 w-8 rounded object-contain"
                    onError={(e) => {
                      (e.target as HTMLImageElement).src = 'https://render.albiononline.com/v1/item/T4_BAG.png?size=40&quality=1';
                    }}
                  />
                  <span className="mr-2">{item.localizedNames['PT-BR'] || item.localizedNames['EN-US'] || item.uniqueName}</span>
                  <button
                    onClick={() => removeItem(item.uniqueName)}
                    className="ml-2 text-muted-foreground hover:text-foreground"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
              ))}
            </div>
          )}
        </div>

        <Separator />

        <div className="space-y-6">
          <HeadingSmall 
            title="Cidade para Comparação" 
            description="Selecione a cidade para comparar com o Black Market" 
          />
          
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-5">
            {['Caerleon', 'Bridgewatch', 'Fort Sterling', 'Lymhurst', 'Martlock', 'Thetford'].map((city) => (
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
            onClick={getPrices}
            disabled={loading || selectedItems.length === 0}
            size="lg"
            className="min-w-[200px]"
          >
            {loading ? (
              <>
                <svg className="mr-2 h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                  <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Consultando...
              </>
            ) : (
              'Comparar Preços'
            )}
          </Button>
        </div>

        {prices.length > 0 && (
          <>
            <Separator />
            
            <div className="space-y-6">
              <HeadingSmall 
                title="Comparação de Preços" 
                description={`Diferença de preços entre o Black Market e ${selectedCity}`} 
              />
              
              <div className="rounded-md border border-border">
                <div className="overflow-x-auto">
                  <table className="w-full divide-y divide-border">
                    <thead>
                      <tr className="bg-muted">
                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">Item</th>
                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">Preço no Black Market</th>
                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">Preço em {selectedCity}</th>
                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">Diferença</th>
                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">Ações</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-border bg-background">
                      {selectedItems.map((item) => {
                        const blackMarketPrice = blackMarketPrices.find(
                          price => price.item_id === item.uniqueName && price.city === 'Black Market'
                        )?.sell_price_min || 0;
                        
                        const cityPrice = cityPrices.find(
                          price => price.item_id === item.uniqueName && price.city === selectedCity
                        )?.sell_price_min || 0;
                        
                        const { difference, percentDifference } = calculatePriceDifference(item.uniqueName);
                        
                        return (
                          <tr key={item.uniqueName} className="hover:bg-muted/50">
                            <td className="whitespace-nowrap px-6 py-4 text-sm font-medium">
                              <div className="flex items-center">
                                <img 
                                  src={getItemIconUrl(item.uniqueName, 40)} 
                                  alt={getItemName(item.uniqueName)} 
                                  className="mr-3 h-8 w-8 rounded object-contain"
                                  onError={(e) => {
                                    (e.target as HTMLImageElement).src = 'https://render.albiononline.com/v1/item/T4_BAG.png?size=40&quality=1';
                                  }}
                                />
                                <div>
                                  <div>{getItemName(item.uniqueName)}</div>
                                  <div className="text-xs text-muted-foreground">{getItemEnglishName(item.uniqueName)}</div>
                                </div>
                              </div>
                            </td>
                            <td className="whitespace-nowrap px-6 py-4 text-sm text-muted-foreground">{formatPrice(blackMarketPrice)}</td>
                            <td className="whitespace-nowrap px-6 py-4 text-sm text-muted-foreground">{formatPrice(cityPrice)}</td>
                            <td className={`whitespace-nowrap px-6 py-4 text-sm ${getPriceDifferenceClass(difference)}`}>
                              {formatPrice(difference)} ({percentDifference.toFixed(2)}%)
                            </td>
                            <td className="whitespace-nowrap px-6 py-4 text-sm">
                              <Button
                                asChild
                                variant="outline"
                                size="sm"
                              >
                                <Link href={`/albion/item/${item.uniqueName}`}>
                                  Detalhes
                                </Link>
                              </Button>
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </>
        )}
      </div>
    </AlbionLayout>
  );
}
