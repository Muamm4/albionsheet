import { useState, useEffect, useRef } from 'react';
import { debounce } from 'lodash';
import { 
  AlbionItem, 
  searchItems, 
  getItemIconUrl, 
  getBaseItemId, 
  getEnchantmentLevel,
  applyEnchantmentLevel,
  getItemTier,
  applyItemTier
} from '@/utils/albionUtils';
import { 
  Select, 
  SelectContent, 
  SelectItem, 
  SelectTrigger, 
  SelectValue 
} from '@/components/ui/select';

interface AlbionItemSelectorProps {
  onItemSelect: (item: AlbionItem) => void;
  placeholder?: string;
}

export default function AlbionItemSelector({ onItemSelect, placeholder = 'Digite o nome do item...' }: AlbionItemSelectorProps) {
  const [searchTerm, setSearchTerm] = useState('');
  const [suggestions, setSuggestions] = useState<AlbionItem[]>([]);
  const [loading, setLoading] = useState(false);
  const [enchantmentLevel, setEnchantmentLevel] = useState<string>("0");
  const [selectedTier, setSelectedTier] = useState<string>("0");
  const suggestionsRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  // Função para buscar sugestões de itens
  const fetchSuggestions = debounce(async (term: string) => {
    if (!term || term.length < 3) {
      setSuggestions([]);
      return;
    }

    setLoading(true);
    try {
      // Buscar itens base primeiro
      const baseItems = await searchItems(term, 20, parseInt(enchantmentLevel, 10), parseInt(selectedTier, 10));
      
      setSuggestions(baseItems);
    } catch (error) {
      console.error('Erro ao buscar sugestões:', error);
      setSuggestions([]);
    } finally {
      setLoading(false);
    }
  }, 300);

  // Efeito para buscar sugestões quando o termo de busca, tier ou encantamento mudar
  useEffect(() => {
    if (searchTerm.length >= 3) {
      fetchSuggestions(searchTerm);
    }
  }, [searchTerm, selectedTier, enchantmentLevel]);

  // Efeito para fechar o dropdown de sugestões quando clicar fora
  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (suggestionsRef.current && !suggestionsRef.current.contains(event.target as Node) && 
          inputRef.current && !inputRef.current.contains(event.target as Node)) {
        setSuggestions([]);
      }
    }

    document.addEventListener('mousedown', handleClickOutside);
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, []);

  // Função para selecionar um item da lista de sugestões
  const selectItem = (item: AlbionItem) => {
    // Item já tem o tier e encantamento aplicados das sugestões
    onItemSelect(item);
    setSearchTerm('');
    setSuggestions([]);
  };

  return (
    <div className="relative w-full">
      <div className="flex gap-2">
        <div className="relative flex-1">
          <input
            ref={inputRef}
            type="text"
            className="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            placeholder={placeholder}
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
          />
          {loading && (
            <div className="absolute right-3 top-1/2 transform -translate-y-1/2">
              <svg className="animate-spin h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
            </div>
          )}
        </div>
        
        <Select value={selectedTier} onValueChange={setSelectedTier}>
          <SelectTrigger className="w-[100px]">
            <SelectValue placeholder="Tier" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="0">Tier</SelectItem>
            <SelectItem value="1">T1</SelectItem>
            <SelectItem value="2">T2</SelectItem>
            <SelectItem value="3">T3</SelectItem>
            <SelectItem value="4">T4</SelectItem>
            <SelectItem value="5">T5</SelectItem>
            <SelectItem value="6">T6</SelectItem>
            <SelectItem value="7">T7</SelectItem>
            <SelectItem value="8">T8</SelectItem>
          </SelectContent>
        </Select>
        
        <Select value={enchantmentLevel} onValueChange={setEnchantmentLevel}>
          <SelectTrigger className="w-[120px]">
            <SelectValue placeholder="Encantamento" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="0">Nível 0</SelectItem>
            <SelectItem value="1">Nível 1</SelectItem>
            <SelectItem value="2">Nível 2</SelectItem>
            <SelectItem value="3">Nível 3</SelectItem>
            <SelectItem value="4">Nível 4</SelectItem>
          </SelectContent>
        </Select>
      </div>
      
      {/* Lista de sugestões */}
      {suggestions.length > 0 && (
        <div 
          ref={suggestionsRef}
          className="absolute z-10 w-full mt-1 bg-white dark:bg-gray-700 border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto"
        >
          {suggestions.map((item) => {
            // Extrair o ID base do item sem o nível de encantamento
            const baseItemId = item.uniquename;
            
            return (
              <div
                key={item.uniquename}
                className="p-3 hover:bg-gray-100 dark:hover:bg-gray-600 cursor-pointer flex items-start gap-3"
                onClick={() => selectItem(item)}
              >
                <div className="flex-shrink-0">
                  <img 
                    src={getItemIconUrl(baseItemId, 64)} 
                    alt={item.nicename} 
                    className="w-12 h-12 object-contain rounded bg-gray-100 dark:bg-gray-800"
                    onError={(e) => {
                      // Fallback para ícones que não carregam
                      (e.target as HTMLImageElement).src = 'https://render.albiononline.com/v1/item/T4_BAG.png?size=64&quality=1';
                    }}
                  />
                </div>
                <div className="flex-1 min-w-0">
                  <div className="font-medium">
                    {item.nicename || baseItemId}
                  </div>
                  <div className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    {item.tier ? `Tier ${item.tier}` : ''}
                    {item.enchantment_level && item.enchantment_level > 0 ? ` | Encantamento ${item.enchantment_level}` : ''}
                  </div>
                  <div className="text-xs text-gray-400 dark:text-gray-500 mt-1 truncate">
                    {item.uniquename}
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
