import React, { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { LayoutDashboard, ShoppingCart, Warehouse, Settings, Users, ShieldCheck as UserShield, Box, Truck, LineChart, History, LogOut, ChevronDown } from 'lucide-react';
import { useToast } from '@/components/ui/use-toast';

const mainItems = [
  { id: 'dashboard', label: 'Dashboard', icon: LayoutDashboard },
  { id: 'transactions', label: 'Transactions', icon: ShoppingCart },
  { id: 'inventory', label: 'Inventory', icon: Warehouse },
];

const maintenanceItems = [
  { id: 'users', label: 'User Accounts', icon: Users },
  { id: 'permissions', label: 'User Permissions', icon: UserShield },
  { id: 'products', label: 'Products', icon: Box },
  { id: 'suppliers', label: 'Suppliers', icon: Truck },
];

const analyticsItems = [
  { id: 'reports', label: 'Reports', icon: LineChart },
  { id: 'activity', label: 'Activity Log', icon: History },
];

const Sidebar = ({ activeSection, setActiveSection, collapsed, setCollapsed }) => {
  const [isMaintenanceOpen, setMaintenanceOpen] = useState(true);
  const { toast } = useToast();

  const handleLogout = () => {
    toast({
      title: '🚧 Feature Not Implemented',
      description: "This feature isn't implemented yet—but don't worry! You can request it in your next prompt! 🚀",
    });
  };

  return (
    <motion.div
      animate={{ width: collapsed ? 80 : 256 }}
      transition={{ duration: 0.3, ease: 'easeInOut' }}
      className="fixed left-0 top-0 h-screen bg-[#2c1a1a] text-gray-200 z-50 flex flex-col"
    >
      <div className="flex items-center justify-between p-4 h-16 border-b border-white/10 shrink-0">
        {!collapsed && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="flex items-center space-x-2"
          >
            <div className="w-8 h-8 bg-gradient-to-br from-red-400 to-red-600 rounded-lg flex items-center justify-center">
              <span className="text-white font-bold text-sm">M</span>
            </div>
            <span className="text-white font-bold text-lg">MikeMadz</span>
          </motion.div>
        )}
        <button
          onClick={() => setCollapsed(!collapsed)}
          className="p-2 rounded-lg hover:bg-white/10 transition-colors text-white"
        >
          {collapsed ? <span>&rarr;</span> : <span>&larr;</span>}
        </button>
      </div>

      <nav className="flex-1 overflow-y-auto sidebar p-2">
        <SidebarSection title="Main" items={mainItems} activeSection={activeSection} setActiveSection={setActiveSection} collapsed={collapsed} />
        
        <div className="mt-4">
          {!collapsed && <div className="px-3 py-2 text-xs font-semibold uppercase text-gray-400">Management</div>}
          <button
            onClick={() => !collapsed && setMaintenanceOpen(!isMaintenanceOpen)}
            className="w-full flex items-center text-left p-3 rounded-lg hover:bg-white/5"
          >
            <Settings size={20} className="mr-3 shrink-0" />
            {!collapsed && <span className="flex-1">Maintenance</span>}
            {!collapsed && (
              <ChevronDown
                size={16}
                className={`transition-transform duration-300 ${isMaintenanceOpen ? 'rotate-180' : ''}`}
              />
            )}
          </button>
          <AnimatePresence>
            {isMaintenanceOpen && !collapsed && (
              <motion.div
                initial={{ height: 0, opacity: 0 }}
                animate={{ height: 'auto', opacity: 1 }}
                exit={{ height: 0, opacity: 0 }}
                className="overflow-hidden pl-5"
              >
                {maintenanceItems.map((item) => (
                  <SidebarItem
                    key={item.id}
                    item={item}
                    isActive={activeSection === item.id}
                    onClick={() => setActiveSection(item.id)}
                    collapsed={false}
                    isSubItem={true}
                  />
                ))}
              </motion.div>
            )}
          </AnimatePresence>
        </div>

        <SidebarSection title="Analytics" items={analyticsItems} activeSection={activeSection} setActiveSection={setActiveSection} collapsed={collapsed} />
      </nav>

      <div className="p-4 border-t border-white/10 shrink-0">
        <button
          onClick={handleLogout}
          className="w-full flex items-center justify-center p-3 rounded-lg bg-red-800/50 hover:bg-red-700/80 transition-colors text-red-200"
        >
          <LogOut size={20} />
          {!collapsed && <span className="ml-3 font-medium">Logout</span>}
        </button>
      </div>
    </motion.div>
  );
};

const SidebarSection = ({ title, items, activeSection, setActiveSection, collapsed }) => (
  <div className="mt-4">
    {!collapsed && <div className="px-3 py-2 text-xs font-semibold uppercase text-gray-400">{title}</div>}
    {items.map((item) => (
      <SidebarItem
        key={item.id}
        item={item}
        isActive={activeSection === item.id}
        onClick={() => setActiveSection(item.id)}
        collapsed={collapsed}
      />
    ))}
  </div>
);

const SidebarItem = ({ item, isActive, onClick, collapsed, isSubItem = false }) => {
  const Icon = item.icon;
  const itemClasses = `w-full flex items-center text-left p-3 rounded-lg transition-colors duration-200 relative overflow-hidden
    ${isActive ? 'bg-red-600 text-white' : 'hover:bg-white/5'}
    ${collapsed ? 'justify-center' : ''}
    ${isSubItem ? 'text-sm' : ''}
  `;

  return (
    <button onClick={onClick} className={itemClasses}>
      <Icon size={20} className={`shrink-0 ${!collapsed ? 'mr-3' : ''}`} />
      {!collapsed && <span className="truncate">{item.label}</span>}
      {isActive && (
        <div className="absolute left-0 top-0 h-full w-1 bg-white rounded-r-full" />
      )}
    </button>
  );
};

export default Sidebar;