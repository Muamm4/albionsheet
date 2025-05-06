import { useState, useEffect } from 'react';
import { Link } from '@inertiajs/react';
import AlbionLayout from '@/layouts/albion/layout';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import HeadingSmall from '@/components/heading-small';
import { AlbionItem, getItemIconUrl } from '@/utils/albionUtils';

export default function AlbionFavorites() {
  const [favorites, setFavorites] = useState<AlbionItem[]>([]);
  const [loading, setLoading] = useState(true);

  // Carregar favoritos do localStorage
  useEffect(() => {
    const loadFavorites = () => {
      try {
        const storedFavorites = localStorage.getItem('albionFavorites');
        if (storedFavorites) {
          setFavorites(JSON.parse(storedFavorites));
        }
      } catch (error) {
        console.error('Erro ao carregar favoritos:', error);
      } finally {
        setLoading(false);
      }
    };

    loadFavorites();
  }, []);

  // Remover um item dos favoritos
  const removeFavorite = (uniqueName: string) => {
    const updatedFavorites = favorites.filter(item => item.uniqueName !== uniqueName);
    setFavorites(updatedFavorites);
    localStorage.setItem('albionFavorites', JSON.stringify(updatedFavorites));
  };

  return (
    <AlbionLayout
      title="Itens Favoritos"
      description="Gerencie seus itens favoritos do Albion Online"
    >
      <div className="space-y-8">
        {loading ? (
          <div className="flex h-64 items-center justify-center">
            <div className="flex flex-col items-center">
              <svg className="h-12 w-12 animate-spin text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <p className="mt-4 text-lg font-medium">Carregando favoritos...</p>
            </div>
          </div>
        ) : (
          <>
            <div className="space-y-6">
              <HeadingSmall 
                title="Seus Itens Favoritos" 
                description="Itens que você marcou como favoritos para acesso rápido" 
              />
              
              {favorites.length === 0 ? (
                <div className="rounded-lg border border-border bg-muted/30 p-8 text-center">
                  <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-muted">
                    <svg xmlns="http://www.w3.org/2000/svg" className="h-6 w-6 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                    </svg>
                  </div>
                  <h3 className="mb-2 text-lg font-medium">Nenhum item favorito</h3>
                  <p className="text-muted-foreground">
                    Você ainda não adicionou nenhum item aos favoritos. Visite a página de consulta de preços e clique no ícone de coração para adicionar itens aos favoritos.
                  </p>
                  <Button className="mt-6" asChild>
                    <Link href="/albion">Ir para Consulta de Preços</Link>
                  </Button>
                </div>
              ) : (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                  {favorites.map((item) => (
                    <div key={item.uniqueName} className="flex flex-col rounded-lg border border-border bg-card p-4 shadow-sm transition-all hover:shadow-md">
                      <div className="flex items-center space-x-4">
                        <div className="flex h-16 w-16 items-center justify-center rounded-md bg-muted p-2">
                          <img 
                            src={getItemIconUrl(item.uniqueName, 64)} 
                            alt={item.localizedNames['PT-BR'] || item.uniqueName} 
                            className="h-full w-full object-contain"
                            onError={(e) => {
                              (e.target as HTMLImageElement).src = 'https://render.albiononline.com/v1/item/T4_BAG.png?size=64&quality=1';
                            }}
                          />
                        </div>
                        <div className="flex-1 min-w-0">
                          <h3 className="truncate text-sm font-medium">{item.localizedNames['PT-BR'] || item.uniqueName}</h3>
                          {item.localizedNames['EN-US'] && item.localizedNames['PT-BR'] !== item.localizedNames['EN-US'] && (
                            <p className="truncate text-xs text-muted-foreground">{item.localizedNames['EN-US']}</p>
                          )}
                        </div>
                      </div>
                      
                      <div className="mt-4 flex justify-between space-x-2">
                        <Button variant="outline" size="sm" className="flex-1" asChild>
                          <Link href={`/albion/item/${item.uniqueName}`}>Detalhes</Link>
                        </Button>
                        <Button 
                          variant="ghost" 
                          size="sm"
                          onClick={() => removeFavorite(item.uniqueName)}
                          className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                        >
                          <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                          </svg>
                        </Button>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </>
        )}
      </div>
    </AlbionLayout>
  );
}
