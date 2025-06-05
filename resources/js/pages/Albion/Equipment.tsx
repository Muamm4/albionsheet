import React, { useState, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import AlbionLayout from '@/layouts/albion/layout';
import { getTierName, getEnchantmentName, getItemImageUrl } from '@/utils/albionUtils';
import LoadingIndicator from '@/components/LoadingIndicator';
import { Link } from '@inertiajs/react';

interface EquipmentItem {
  id: number;
  uniquename: string;
  nicename: string;
  tier: number;
  enchantment: number;
  shop_category: string;
  shop_subcategory1: string;
  item_power: number;
}

interface SubcategoryGroup {
  subcategory: string;
  items: EquipmentItem[];
}

const Equipment = () => {
  const [loading, setLoading] = useState(true);
  const [equipmentData, setEquipmentData] = useState<SubcategoryGroup[]>([]);
  const [selectedTier, setSelectedTier] = useState<number>(0);
  const [selectedEnchantment, setSelectedEnchantment] = useState<number>(-1);
  const [selectedCategory, setSelectedCategory] = useState<string>('');
  const [selectedSubcategory, setSelectedSubcategory] = useState<string>('');
  const [subcategories, setSubcategories] = useState<string[]>([]);
  const [error, setError] = useState<string | null>(null);

  // Categorias disponíveis
  const categories = [
    { value: '', label: 'Todas Categorias' },
    { value: 'accessories', label: 'Acessórios' },
    { value: 'armor', label: 'Armaduras' },
    { value: 'magic', label: 'Armas Mágicas' },
    { value: 'melee', label: 'Armas Corpo a Corpo' },
    { value: 'ranged', label: 'Armas à Distância' },
    { value: 'offhand', label: 'Itens Secundários' }
  ];

  // Tiers disponíveis
  const tiers = [
    { value: 0, label: 'Todos Tiers' },
    { value: 4, label: 'T4' },
    { value: 5, label: 'T5' },
    { value: 6, label: 'T6' },
    { value: 7, label: 'T7' },
    { value: 8, label: 'T8' }
  ];

  // Níveis de encantamento disponíveis
  const enchantments = [
    { value: -1, label: 'Todos Encantamentos' },
    { value: 0, label: 'Sem Encantamento' },
    { value: 1, label: 'Encantamento 1' },
    { value: 2, label: 'Encantamento 2' },
    { value: 3, label: 'Encantamento 3' },
    { value: 4, label: 'Encantamento 4' }
  ];

  // Buscar dados de equipamentos com os filtros aplicados
  const fetchEquipmentData = async () => {
    setLoading(true);
    setError(null);

    try {
      const params = {
        tier: selectedTier,
        enchantment: selectedEnchantment,
        category: selectedCategory,
        subcategory: selectedSubcategory
      };

      const response = await axios.get('/api/albion/equipment', { params });
      setEquipmentData(response.data);

      // Extrair subcategorias únicas dos dados
      if (response.data.length > 0) {
        const uniqueSubcategories = Array.from(
          new Set(response.data.map((group: SubcategoryGroup) => group.subcategory))
        ) as string[];
        setSubcategories(uniqueSubcategories);
      }
    } catch (err) {
      console.error('Erro ao buscar dados de equipamentos:', err);
      setError('Erro ao carregar dados de equipamentos. Por favor, tente novamente.');
      setEquipmentData([]);
    } finally {
      setLoading(false);
    }
  };

  // Buscar dados iniciais ao carregar a página
  useEffect(() => {
    fetchEquipmentData();
  }, []);

  // Buscar dados quando os filtros mudarem
  useEffect(() => {
    fetchEquipmentData();
  }, [selectedTier, selectedEnchantment, selectedCategory, selectedSubcategory]);

  // Formatar nome da subcategoria
  const formatSubcategoryName = (subcategory: string) => {
    if (!subcategory) return 'Outros';
    
    // Substituir underscores por espaços e capitalizar cada palavra
    return subcategory
      .split('_')
      .map(word => word.charAt(0).toUpperCase() + word.slice(1))
      .join(' ');
  };

  return (
    <AlbionLayout title="Equipamentos" description="Lista de equipamentos do Albion Online">
      <Head title="Equipamentos - Albion Sheet" />
      
      <div className="container mx-auto">
        <h1 className="text-3xl font-bold mb-6">Equipamentos do Albion Online</h1>
        
        {/* Filtros */}
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-6">
          <h2 className="text-xl font-semibold mb-4">Filtros</h2>
          
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            {/* Filtro de Categoria */}
            <div>
              <label className="block text-sm font-medium mb-1">Categoria</label>
              <select
                className="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                value={selectedCategory}
                onChange={(e) => {
                  setSelectedCategory(e.target.value);
                  setSelectedSubcategory(''); // Resetar subcategoria ao mudar categoria
                }}
              >
                {categories.map((category) => (
                  <option key={category.value} value={category.value}>
                    {category.label}
                  </option>
                ))}
              </select>
            </div>
            
            {/* Filtro de Subcategoria */}
            <div>
              <label className="block text-sm font-medium mb-1">Subcategoria</label>
              <select
                className="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                value={selectedSubcategory}
                onChange={(e) => setSelectedSubcategory(e.target.value)}
              >
                <option value="">Todas Subcategorias</option>
                {subcategories.map((subcategory) => (
                  <option key={subcategory} value={subcategory}>
                    {formatSubcategoryName(subcategory)}
                  </option>
                ))}
              </select>
            </div>
            
            {/* Filtro de Tier */}
            <div>
              <label className="block text-sm font-medium mb-1">Tier</label>
              <select
                className="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                value={selectedTier}
                onChange={(e) => setSelectedTier(Number(e.target.value))}
              >
                {tiers.map((tier) => (
                  <option key={tier.value} value={tier.value}>
                    {tier.label}
                  </option>
                ))}
              </select>
            </div>
            
            {/* Filtro de Encantamento */}
            <div>
              <label className="block text-sm font-medium mb-1">Encantamento</label>
              <select
                className="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                value={selectedEnchantment}
                onChange={(e) => setSelectedEnchantment(Number(e.target.value))}
              >
                {enchantments.map((enchantment) => (
                  <option key={enchantment.value} value={enchantment.value}>
                    {enchantment.label}
                  </option>
                ))}
              </select>
            </div>
          </div>
        </div>
        
        {/* Conteúdo */}
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow">
          {loading ? (
            <div className="flex justify-center items-center p-12">
              <LoadingIndicator />
            </div>
          ) : error ? (
            <div className="p-6 text-center text-red-500">{error}</div>
          ) : equipmentData.length === 0 ? (
            <div className="p-6 text-center">Nenhum equipamento encontrado com os filtros selecionados.</div>
          ) : (
            <div className="p-4">
              {equipmentData.map((group) => (
                <div key={group.subcategory} className="mb-8">
                  <h2 className="text-xl font-bold mb-4 border-b pb-2">
                    {formatSubcategoryName(group.subcategory)}
                  </h2>
                  
                  <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                    {group.items.map((item) => (
                      <Link
                        key={item.uniquename}
                        href={`/albion/item/${item.uniquename}`}
                        className="bg-gray-50 dark:bg-gray-700 rounded-lg p-3 hover:shadow-lg transition-shadow flex flex-col items-center"
                      >
                        <div className="w-16 h-16 mb-2 relative">
                          <img
                            src={getItemImageUrl(item.uniquename)}
                            alt={item.nicename}
                            className="w-full h-full object-contain"
                            onError={(e) => {
                              (e.target as HTMLImageElement).src = '/images/placeholder.png';
                            }}
                          />
                        </div>
                        <div className="text-center">
                          <div className="font-semibold text-sm">
                            {getTierName(item.tier)}{getEnchantmentName(item.enchantment)} {item.nicename}
                          </div>
                          <div className="text-xs text-gray-500 dark:text-gray-400">
                            Item Power: {item.item_power}
                          </div>
                        </div>
                      </Link>
                    ))}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </AlbionLayout>
  );
};

export default Equipment;
