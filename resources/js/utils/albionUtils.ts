/**
 * Utilitários para a aplicação Albion Online Price Checker
 */

import axios from 'axios';

/**
 * Interface para os itens do Albion Online
 */
export interface AlbionItem {
  id: string;
  nicename: string;
  uniquename: string;
  tier?: number;
  enchantment_level?: number;
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

export const searchItems = async (searchTerm: string, limit: number = 10, enchantmentLevel: number = 0, tier: number = 0): Promise<AlbionItem[]> => {
  if (!searchTerm || searchTerm.length < 3) {
    return [];
  }

  try {
    // Buscar os dados dos itens do arquivo JSON
    const response = await axios.get('../api/albion/items/list');
    const items = response.data;

    // Limpar o termo de busca para comparações
    const cleanSearchTerm = searchTerm.toLowerCase();
    
    // Filtrar os itens que correspondem ao termo de busca, tier e encantamento
    const filtered = items
      // Filtramos apenas itens base (sem encantamento no ID)
      .filter((item: any) => !item.uniquename.includes('@'))
      .filter((item: any) => {
        const niceName = item.nicename?.toLowerCase() || '';
        const uniqueName = item.uniquename?.toLowerCase() || '';
        
        // Verificar se o item contém o termo de busca (condição obrigatória)
        const matchesTerm = niceName.includes(cleanSearchTerm) || uniqueName.includes(cleanSearchTerm);
        if (!matchesTerm) return false; // Se não corresponde ao termo de busca, já descarta
        
        // Verificar tier se especificado
        if (tier > 0) {
            const tierPattern = "t" + tier.toString();
            const matchesTier = uniqueName.includes(tierPattern);
            if (!matchesTier) return false; // Se não corresponde ao tier, já descarta
        }
        
        // Se passou por todas as verificações, o item corresponde aos critérios
        return true;
      })
      .map((item: any) => {
        // Obter o ID base do item
        const baseItemId = item.uniquename;
        
        // Aplicar tier se especificado (caso o item não tenha o tier correto)
        let processedItemId = baseItemId;
        if (tier > 0) {
          processedItemId = applyItemTier(processedItemId, tier);
        }
        
        // Aplicar encantamento se necessário
        const uniquename = enchantmentLevel > 0 ? 
          applyEnchantmentLevel(processedItemId, enchantmentLevel) : 
          processedItemId;
        
        return {
          id: item.id || '',
          nicename: item.nicename || uniquename,
          uniquename: uniquename,
          tier: item.tier || getItemTier(uniquename),
          enchantment_level: enchantmentLevel || item.enchantment_level || 0
        };
      })
      .sort((a: AlbionItem, b: AlbionItem) => {
        // Ordenar primeiro pelos itens que começam com o termo de busca
        const aNiceName = a.nicename?.toLowerCase() || '';
        const aUniqueName = a.uniquename?.toLowerCase() || '';
        const bNiceName = b.nicename?.toLowerCase() || '';
        const bUniqueName = b.uniquename?.toLowerCase() || '';
        
        // Priorizar itens que começam com o termo de busca
        const aStartsWithNice = aNiceName.startsWith(cleanSearchTerm);
        const aStartsWithUnique = aUniqueName.startsWith(cleanSearchTerm);
        const bStartsWithNice = bNiceName.startsWith(cleanSearchTerm);
        const bStartsWithUnique = bUniqueName.startsWith(cleanSearchTerm);
        
        if ((aStartsWithNice || aStartsWithUnique) && !(bStartsWithNice || bStartsWithUnique)) {
          return -1;
        }
        if (!(aStartsWithNice || aStartsWithUnique) && (bStartsWithNice || bStartsWithUnique)) {
          return 1;
        }
        
        // Se ambos começam ou nenhum começa, ordenar por relevância
        const aRelevance = Math.min(
          aNiceName.includes(cleanSearchTerm) ? aNiceName.indexOf(cleanSearchTerm) : Infinity,
          aUniqueName.includes(cleanSearchTerm) ? aUniqueName.indexOf(cleanSearchTerm) : Infinity
        );
        const bRelevance = Math.min(
          bNiceName.includes(cleanSearchTerm) ? bNiceName.indexOf(cleanSearchTerm) : Infinity,
          bUniqueName.includes(cleanSearchTerm) ? bUniqueName.indexOf(cleanSearchTerm) : Infinity
        );
        
        // Se a relevância for igual, ordenar por tier
        if (aRelevance === bRelevance) {
          return (a.tier || 0) - (b.tier || 0);
        }
        
        return aRelevance - bRelevance;
      })
      .slice(0, limit);
    
    return filtered;
  } catch (error) {
    console.error('Erro ao buscar itens:', error);
    return [];
  }
};

export const fetchItemPrices = async (
  items: string[], 
  locations: string[] = ['Bridgewatch'],
  quality: number = 0
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
      quality,
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
 * Extrai o tier do item
 * 
 * @param itemId ID do item (ex: T4_BAG@2)
 * @returns Tier do item (ex: 4)
 */
export const getItemTier = (itemId: string): number => {
  if (!itemId || !itemId.startsWith('T')) return 0;
  const tierStr = itemId.charAt(1);
  return parseInt(tierStr, 10) || 0;
};

/**
 * Altera o tier de um item
 * 
 * @param itemId ID do item (ex: T4_BAG@2)
 * @param tier Novo tier (1-8)
 * @returns ID do item com o novo tier (ex: T5_BAG@2)
 */
export const applyItemTier = (itemId: string, tier: number): string => {
  if (!itemId || tier < 1 || tier > 8) return itemId;
  
  // Remover o tier atual e substituir pelo novo
  if (itemId.startsWith('T') && itemId.length > 2) {
    return `T${tier}${itemId.substring(2)}`;
  }
  
  return itemId;
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
