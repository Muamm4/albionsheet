/**
 * Utilitários para a aplicação Albion Online Price Checker
 */

import axios from 'axios';

/**
 * Interface para os itens do Albion Online
 */
export interface AlbionItem {
  id: string;
  name: string;
  localizedNames: {
    'PT-BR'?: string;
    'EN-US'?: string;
    [key: string]: string | undefined;
  };
  uniqueName: string;
}

/**
 * Interface para os preços dos itens
 */
export interface ItemPrice {
  item_id: string;
  city: string;
  quality: number;
  sell_price_min: number;
  sell_price_min_date?: string;
  sell_price_max: number;
  sell_price_max_date?: string;
  buy_price_min: number;
  buy_price_min_date?: string;
  buy_price_max: number;
  buy_price_max_date?: string;
}

/**
 * Interface para os materiais de crafting
 */
export interface Material {
  itemId: string;
  name: string;
  quantity: number;
  price: number;
}

/**
 * Interface para as informações de crafting
 */
export interface CraftingInfo {
  materials: Material[];
  totalCost: number;
  returnRate?: number;
  craftingFee?: number;
}

/**
 * Obtém a URL do ícone do item
 * 
 * @param itemId ID único do item
 * @param size Tamanho do ícone (padrão: 100)
 * @param quality Qualidade do item (1-5, padrão: 1)
 * @returns URL do ícone
 */
export const getItemIconUrl = (itemId: string, size: number = 100, quality: number = 1): string => {
  
  // URL base do serviço de renderização do Albion Online
  const baseUrl = 'https://render.albiononline.com/v1/item';
  
  return `${baseUrl}/${itemId}.png?size=${size}&quality=${quality}`;
};

/**
 * Busca os itens do Albion Online que correspondem ao termo de busca
 * 
 * @param searchTerm Termo de busca
 * @param limit Limite de resultados
 * @returns Lista de itens filtrados
 */
export const searchItems = async (searchTerm: string, limit: number = 10): Promise<AlbionItem[]> => {
  if (!searchTerm || searchTerm.length < 3) {
    return [];
  }

  try {
    // Buscar os dados dos itens do arquivo JSON
    const response = await axios.get('/items.json');
    const items = response.data;
    
    // Filtrar os itens que correspondem ao termo de busca
    const filtered = items
      .filter((item: any) => {
        const ptName = item.LocalizedNames?.['PT-BR']?.toLowerCase() || '';
        const enName = item.LocalizedNames?.['EN-US']?.toLowerCase() || '';
        const uniqueName = item.UniqueName?.toLowerCase() || '';
        const term_lower = searchTerm.toLowerCase();
        
        return ptName.includes(term_lower) || 
               enName.includes(term_lower) || 
               uniqueName.includes(term_lower);
      })
      .map((item: any) => ({
        id: item.Index || '',
        name: item.LocalizedNames?.['EN-US'] || item.LocalizedNames?.['PT-BR'] || item.UniqueName,
        localizedNames: item.LocalizedNames || {},
        uniqueName: item.UniqueName
      }))
      .sort((a: AlbionItem, b: AlbionItem) => {
        // Ordenar primeiro pelos itens que começam com o termo de busca
        const term_lower = searchTerm.toLowerCase();
        const aPtName = a.localizedNames['PT-BR']?.toLowerCase() || '';
        const aEnName = a.localizedNames['EN-US']?.toLowerCase() || '';
        const bPtName = b.localizedNames['PT-BR']?.toLowerCase() || '';
        const bEnName = b.localizedNames['EN-US']?.toLowerCase() || '';
        
        // Priorizar itens que começam com o termo de busca
        const aStartsWithPt = aPtName.startsWith(term_lower);
        const aStartsWithEn = aEnName.startsWith(term_lower);
        const bStartsWithPt = bPtName.startsWith(term_lower);
        const bStartsWithEn = bEnName.startsWith(term_lower);
        
        if ((aStartsWithPt || aStartsWithEn) && !(bStartsWithPt || bStartsWithEn)) {
          return -1;
        }
        if (!(aStartsWithPt || aStartsWithEn) && (bStartsWithPt || bStartsWithEn)) {
          return 1;
        }
        
        // Se ambos começam ou nenhum começa, ordenar por relevância
        const aRelevance = Math.max(
          aPtName.includes(term_lower) ? aPtName.indexOf(term_lower) : Infinity,
          aEnName.includes(term_lower) ? aEnName.indexOf(term_lower) : Infinity
        );
        const bRelevance = Math.max(
          bPtName.includes(term_lower) ? bPtName.indexOf(term_lower) : Infinity,
          bEnName.includes(term_lower) ? bEnName.indexOf(term_lower) : Infinity
        );
        
        return aRelevance - bRelevance;
      })
      .slice(0, limit);
    
    return filtered;
  } catch (error) {
    console.error('Erro ao buscar itens:', error);
    return [];
  }
};

/**
 * Busca os preços dos itens selecionados
 * 
 * @param items Lista de IDs de itens
 * @param locations Lista de localizações
 * @returns Lista de preços
 */
export const fetchItemPrices = async (
  items: string[], 
  locations: string[] = ['Caerleon']
): Promise<ItemPrice[]> => {
  try {
    // Verificar se há itens para buscar
    if (!items || items.length === 0) {
      console.warn('Nenhum item fornecido para buscar preços');
      return [];
    }

    // Usar a rota correta para buscar preços
    const response = await axios.post('/albion/prices', {
      items,
      locations,
    });
    
    // Verificar se a resposta contém dados válidos
    if (!response.data || !Array.isArray(response.data)) {
      console.warn('Resposta inválida da API de preços:', response.data);
      return [];
    }
    
    return response.data;
  } catch (error) {
    console.error('Erro ao buscar preços:', error);
    // Retornar array vazio em vez de lançar erro para evitar quebrar a UI
    return [];
  }
};

/**
 * Formata um preço para exibição
 * 
 * @param price Preço a ser formatado
 * @returns Preço formatado
 */
export const formatPrice = (price: number): string => {
  if (!price) return 'N/A';
  return new Intl.NumberFormat('pt-BR').format(price);
};

/**
 * Retorna o nome da qualidade do item
 * 
 * @param quality Número da qualidade
 * @returns Nome da qualidade
 */
export const getQualityName = (quality: number): string => {
  const qualities = ['Normal', 'Bom', 'Excepcional', 'Excelente', 'Obra-prima'];
  return qualities[quality - 1] || 'Desconhecido';
};

/**
 * Extrai o nome base do item sem o nível de encantamento
 * 
 * @param itemId ID do item (ex: T4_BAG@2)
 * @returns Nome base do item (ex: T4_BAG)
 */
export const getBaseItemId = (itemId: string): string => {
  if (!itemId) return '';
  return itemId.split('@')[0];
};

/**
 * Obtém o nível de encantamento do item
 * 
 * @param itemId ID do item (ex: T4_BAG@2)
 * @returns Nível de encantamento (0 se não tiver encantamento)
 */
export const getEnchantmentLevel = (itemId: string): number => {
  if (!itemId || !itemId.includes('@')) return 0;
  const level = parseInt(itemId.split('@')[1], 10);
  return isNaN(level) ? 0 : level;
};

/**
 * Aplica um nível de encantamento a um item
 * 
 * @param baseItemId ID base do item (ex: T4_BAG)
 * @param level Nível de encantamento (1-4)
 * @returns ID do item com encantamento (ex: T4_BAG@2)
 */
export const applyEnchantmentLevel = (baseItemId: string, level: number): string => {
  if (!baseItemId || level <= 0) return baseItemId;
  return `${baseItemId}@${level}`;
};

/**
 * Lista de cidades disponíveis no Albion Online
 */
export const ALBION_CITIES = [
  'Bridgewatch', 
  'Caerleon', 
  'Fort Sterling', 
  'Lymhurst', 
  'Martlock', 
  'Thetford'
];
