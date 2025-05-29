import { useState } from 'react';
import { Link } from '@inertiajs/react';
import { 
  AlbionItem, 
  ItemPrice, 
  fetchItemPrices, 
  formatPrice, 
  getQualityName, 
  ALBION_CITIES,
  getItemIconUrl,
  getBaseItemId,
  getEnchantmentLevel
} from '@/utils/albionUtils';
import AlbionItemSelector from '@/components/AlbionItemSelector';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import HeadingSmall from '@/components/heading-small';
import AlbionLayout from '@/layouts/albion/layout';

// Componente principal
export default function AlbionIndex() {
  const [selectedItems, setSelectedItems] = useState<AlbionItem[]>([]);
  const [prices, setPrices] = useState<ItemPrice[]>([]);
  const [loading, setLoading] = useState(false);
  const [selectedCity, setSelectedCity] = useState<string>('Bridgewatch');

  // Breadcrumbs para navegação
  const breadcrumbs = [
    {
      title: 'Albion Online',
      href: '/albion',
    },
    {
      title: 'Consulta de Preços',
      href: '/albion',
    },
  ];

  // Função para adicionar um item à lista de selecionados
  const addItem = (item: AlbionItem) => {
    if (!selectedItems.some(selected => selected.uniquename === item.uniquename)) {
      setSelectedItems([...selectedItems, item]);
    }
  };

  // Função para remover um item selecionado
  const removeItem = (uniqueName: string) => {
    setSelectedItems(selectedItems.filter(item => item.uniquename !== uniqueName));
  };

  // Função para buscar os preços dos itens selecionados
  const getPrices = async () => {
    if (selectedItems.length === 0) {
      alert('Selecione pelo menos um item para consultar os preços.');
      return;
    }

    setLoading(true);
    try {
      const itemIds = selectedItems.map(item => item.uniquename);
      const priceData = await fetchItemPrices(itemIds, [selectedCity]);
      setPrices(priceData);
    } catch (error) {
      console.error('Erro ao buscar preços:', error);
      alert('Erro ao buscar preços. Tente novamente mais tarde.');
    } finally {
      setLoading(false);
    }
  };

  // Função para obter o nome do item
  const getItemName = (itemId: string) => {
    const item = selectedItems.find(item => item.uniquename === itemId);
    return item?.nicename || item?.uniquename || itemId;
  };

  return (
    <AlbionLayout 
      title="Consulta de Preços" 
      description="Consulte os preços atuais de itens no mercado de Albion Online"
    >
      <div className="space-y-8">
        <div className="space-y-6">
          <HeadingSmall 
            title="Buscar Item" 
            description="Pesquise e selecione os itens que deseja consultar os preços" 
          />
          
          <AlbionItemSelector onItemSelect={addItem} />
        </div>

        <Separator />

        <div className="space-y-6">
          <HeadingSmall 
            title="Itens Selecionados" 
            description="Itens que você selecionou para consultar os preços" 
          />
          
          {selectedItems.length === 0 ? (
            <div className="rounded-md bg-muted p-4 text-center text-sm text-muted-foreground">
              Nenhum item selecionado. Use a busca acima para adicionar itens.
            </div>
          ) : (
            <div className="flex flex-wrap gap-2">
              {selectedItems.map((item) => (
                <div
                  key={item.uniquename}
                  className="flex items-center rounded-md border border-border bg-background px-3 py-2"
                >
                  <img 
                    src={getItemIconUrl(item.uniquename, 40)} 
                    alt={item.nicename || item.uniquename} 
                    className="mr-2 h-8 w-8 rounded object-contain"
                    onError={(e) => {
                      (e.target as HTMLImageElement).src = 'https://render.albiononline.com/v1/item/T4_BAG.png?size=40&quality=1';
                    }}
                  />
                  <span className="mr-2">{item.nicename || item.uniquename}</span>
                  <button
                    onClick={() => removeItem(item.uniquename)}
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

        <div className="flex justify-center">
          <Button
            onClick={getPrices}
            disabled={selectedItems.length === 0 || loading}
            className="flex items-center gap-2"
          >
            {loading ? (
              <>
                <svg className="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                  <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Consultando...</span>
              </>
            ) : (
              <>
                <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <span>Consultar Preços em {selectedCity}</span>
              </>
            )}
          </Button>
        </div>

        {prices.length > 0 && (
          <>
            <Separator />
            
            <div className="space-y-6">
              <HeadingSmall 
                title="Resultados" 
                description="Preços encontrados para os itens selecionados" 
              />
              
              <div className="rounded-md border border-border">
                <div className="overflow-x-auto">
                  <table className="w-full divide-y divide-border">
                    <thead>
                      <tr className="bg-muted">
                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">Item</th>
                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">Cidade</th>
                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">Qualidade</th>
                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">Preço de Venda</th>
                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">Preço de Compra</th>
                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">Atualizado</th>
                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">Ações</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-border bg-background">
                      {prices.map((price, index) => {
                        console.log(price);
                        const item = selectedItems.find(item => item.uniquename === price.item_id);
                        
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
                          : '';
                        
                        return (
                          <tr key={`${price.item_id}-${price.city}-${price.quality}-${index}`} className="hover:bg-muted/50">
                            <td className="whitespace-nowrap px-6 py-4 text-sm font-medium">
                              <div className="flex items-center">
                                <img 
                                  src={getItemIconUrl(price.item_id, 40, price.quality)} 
                                  alt={item?.nicename || price.item_id} 
                                  className="mr-3 h-8 w-8 rounded object-contain"
                                  onError={(e) => {
                                    (e.target as HTMLImageElement).src = 'https://render.albiononline.com/v1/item/' + price.item_id + '.png?size=40&quality=' + price.quality;
                                  }}
                                />
                                <div>
                                  <div>
                                    {item?.nicename || price.item_id}
                                    {getEnchantmentLevel(price.item_id) > 0 && (
                                      <span className="ml-1 text-xs font-medium text-blue-500">
                                        (Encantamento Nível {getEnchantmentLevel(price.item_id)})
                                      </span>
                                    )}
                                  </div>
                                  <div className="text-xs text-muted-foreground">{item?.nicename}</div>
                                </div>
                              </div>
                            </td>
                            <td className="whitespace-nowrap px-6 py-4 text-sm text-muted-foreground">{price.city}</td>
                            <td className="whitespace-nowrap px-6 py-4 text-sm text-muted-foreground">
                              {getQualityName(price.quality)}
                            </td>
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
                            <td className="whitespace-nowrap px-6 py-4 text-sm">
                              <Button
                                asChild
                                variant="outline"
                                size="sm"
                              >
                                <Link href={`/albion/item/${price.item_id}`}>
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
