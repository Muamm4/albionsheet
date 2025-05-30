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
    var filtered = items;
    if (enchantmentLevel > 0) {
      filtered = filtered.filter((item: any) => getEnchantmentLevel(item.uniquename) === enchantmentLevel);
    }
    if (tier > 0) {
      filtered = filtered.filter((item: any) => getItemTier(item.uniquename) === tier);
    }
    filtered = filtered.filter((item: any) => {
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
        // Ordenar primeiro por tier
        const aTier = a.tier || 0;
        const bTier = b.tier || 0;
        
        if (aTier !== bTier) {
          return aTier - bTier; // Ordem crescente de tier
        }
        
        // Extrair o nome base sem prefixo de tier e sem encantamento
        // Ex: T4_CAPE@2 -> CAPE
        const aBaseNameWithoutTier = a.uniquename?.replace(/^T\d+_/, '') || '';
        const bBaseNameWithoutTier = b.uniquename?.replace(/^T\d+_/, '') || '';
        
        // Remover o encantamento para comparar os nomes base
        const aBaseName = aBaseNameWithoutTier.split('@')[0];
        const bBaseName = bBaseNameWithoutTier.split('@')[0];
        
        // Se os nomes base são diferentes
        if (aBaseName !== bBaseName) {
            // Verificar se um nome é prefixo do outro (ex: CAPE é prefixo de CAPEITEM)
            if (aBaseName.startsWith(bBaseName) && aBaseName !== bBaseName) {
                return 1; // a é mais longo e começa com b, então a vem depois
            }
            if (bBaseName.startsWith(aBaseName) && aBaseName !== bBaseName) {
                return -1; // b é mais longo e começa com a, então b vem depois
            }
            
            // Se não há relação de prefixo, ordenar por nome
            return aBaseName.localeCompare(bBaseName);
        }
        
        // Se os nomes base são iguais, verificar se um tem encantamento e o outro não
        const aHasEnchant = a.uniquename?.includes('@') || false;
        const bHasEnchant = b.uniquename?.includes('@') || false;
        
        if (aHasEnchant !== bHasEnchant) {
            return aHasEnchant ? 1 : -1; // Item sem encantamento vem primeiro
        }
        
        // Se ambos têm ou não têm encantamento, ordenar por comprimento do uniquename
        // Isso garante que nomes mais curtos venham antes (T4_CAPE antes de T4_CAPEITEM)
        const aUniqueName = a.uniquename || '';
        const bUniqueName = b.uniquename || '';
        
        // Se os comprimentos são diferentes e nenhum tem encantamento, ou ambos têm
        if (!aHasEnchant && !bHasEnchant && aUniqueName.length !== bUniqueName.length) {
            return aUniqueName.length - bUniqueName.length;
        }
        
        // Por fim, ordenar por nível de encantamento (menor para maior)
        const aEnchantLevel = a.enchantment_level || 0;
        const bEnchantLevel = b.enchantment_level || 0;
        
        return aEnchantLevel - bEnchantLevel;
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
