class WaiterView {
  constructor(api, toast) {
    this.api = api;
    this.toast = toast;
    this.tables = [];
    this.products = [];
    this.recipes = [];
    this.inventoryMap = {};
    this.categories = [];
    this.activeTable = null;
    this.activeCategory = 'Todo';
    this.pendingItems = [];
    this.selectedTablesForMerge = [];

    this.tableGrid = document.getElementById('table-grid');
    this.categoryChips = document.getElementById('category-chips');
    this.menuItems = document.getElementById('menu-items');
    this.orderItemsList = document.getElementById('order-items-list');
    this.orderSubtotal = document.getElementById('order-subtotal');
    this.orderTotal = document.getElementById('order-total');
    this.activeTableBadge = document.getElementById('active-table-badge');
    this.activeOrderTitle = document.getElementById('active-order-title');
    this.btnSendKitchen = document.getElementById('btn-send-kitchen');
    this.btnCancelOrder = document.getElementById('btn-cancel-order');
    this.btnMergeTables = document.getElementById('btn-merge-tables');

    // Inyectar botón "Agregar Mesa"
    if (this.btnMergeTables) {
      const btnAddTable = document.createElement('button');
      btnAddTable.id = 'btn-add-table';
      btnAddTable.className = 'text-primary hover:text-on-surface font-bold text-xs px-3 py-2 bg-surface-container rounded-lg flex items-center gap-2 ml-2';
      btnAddTable.innerHTML = '<i data-lucide="plus-circle" class="w-4 h-4"></i> Agregar Mesa';
      btnAddTable.addEventListener('click', () => this.handleCreateTable());
      this.btnMergeTables.parentNode.appendChild(btnAddTable);
      if (window.lucide) window.lucide.createIcons();
    }

    this._bindEvents();
  }

  _bindEvents() {
    this.btnSendKitchen.addEventListener('click', () => this._sendToKitchen());
    this.btnCancelOrder.addEventListener('click', () => this._cancelOrder());
    this.btnMergeTables.addEventListener('click', () => this._showMergeModal());

    if (this.menuItems) {
      this.menuItems.addEventListener('click', (e) => {
        const productCard = e.target.closest('button[data-product-id]');
        if (productCard) {
          const productId = productCard.dataset.productId;
          this.handleSelectProduct(productId);
        }
      });
    }

    window.addEventListener('order-ready', (e) => {
      const orderId = e.detail?.orderId || '';
      this.toast.show(`🍽️ ¡Pedido listo en Cocina! (ID: ${orderId.substring(0, 8)})`, 'success');
      this.loadData();
    });
  }

  async loadData() {
    try {
      // Disparar todas las peticiones en paralelo. El tiempo total será el de la petición más lenta, no la suma de todas.
      const [tablesRes, productsRes, recipesRes, inventoryRes, categoriesRes] = await Promise.all([
        this.api.getTables().catch(() => ({ success: false, data: [] })),
        this.api.getProducts().catch(() => ({ success: false, data: [] })),
        this.api.getRecipes().catch(() => ({ success: false, data: [] })),
        this.api.getInventory().catch(() => ({ success: false, data: [] })),
        this.api.getCategories().catch(() => ({ success: false, data: [] }))
      ]);

      this.tables = tablesRes.success ? tablesRes.data : (tablesRes || []);
      this.products = productsRes.success ? productsRes.data : [];
      this.recipes = recipesRes.success ? recipesRes.data : [];
      this.inventory = inventoryRes.success ? inventoryRes.data : [];
      this.inventoryMap = {};
      this.inventory.forEach(item => { this.inventoryMap[item.id] = item; });
      
      // Mapeamos las categorías asegurando que "Plato Especial" esté en los chips del frontend
      let apiCategories = categoriesRes.success 
        ? categoriesRes.data.map(c => typeof c === 'object' ? c.name : c) 
        : ['Acompañamientos', 'Bebestibles', 'Ensaladas', 'Platos de Fondo'];

      this.categories = ['Todo', ...apiCategories];

      this.render();
    } catch (error) {
      console.error("Error cargando datos:", error);
      this.toast.show('Error al sincronizar datos', 'error');
    }
  }

  render() {
    this.renderTables();
    this.renderCategoryChips();
    this.renderMenuItems();
  }

  renderTables() {
    // 1. Auditoría en Consola para saber qué está llegando exactamente
    console.log("=== AUDITORÍA DE MESAS EN RENDER ===");
    console.log("Contenido de this.tables:", this.tables);

    // 2. Buscar el contenedor real probando múltiples selectores comunes de tu UI
    this.tableGrid = document.getElementById('table-grid') ||
      document.querySelector('[data-tables-container]') ||
      document.getElementById('salon-container') ||
      document.getElementById('tables-grid') ||
      document.querySelector('.grid');

    if (!this.tableGrid) {
      console.error("⚠️ ERROR CRÍTICO: No se encontró ningún contenedor válido para las mesas en el HTML.");
      return;
    }

    // 3. Si el array no es válido, mostrar estado de carga o error en la interfaz
    if (!Array.isArray(this.tables) || this.tables.length === 0) {
      this.tableGrid.innerHTML = `<div class="p-4 text-center text-red-500 font-bold">No hay mesas disponibles (Array vacío o inválido)</div>`;
      return;
    }

    const statusStyles = {
      available: { bg: '#22c55e', text: '#fff', label: 'Libre' },
      occupied: { bg: '#ef4444', text: '#fff', label: 'Ocupada' },
      maintenance: { bg: '#6b7280', text: '#fff', label: 'Mant.' },
      reserved: { bg: '#f59e0b', text: '#fff', label: 'Reservada' }
    };

    // 4. Inyección limpia en el DOM
    this.tableGrid.innerHTML = this.tables.map(table => {
      const isActive = this.activeTable === table.id;
      const isSelected = (this.selectedTablesForMerge || []).includes(table.id);
      const s = statusStyles[table.status] || statusStyles.available;
      const border = isSelected ? 'border:4px solid #3b82f6;' : isActive ? 'border:4px solid #ea580c;' : 'border:1px solid rgba(0,0,0,0.1);';

      return `
        <button data-table-id="${table.id}" onclick="app.waiterView.selectTable('${table.id}')"
          style="background:${s.bg};color:${s.text};${border}border-radius:12px;height:85px;display:flex;flex-direction:column;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;min-width:85px;padding:10px;"
          class="${isSelected ? 'selected' : ''} ambient-shadow hover:scale-105">
          <span style="font-size:10px;text-transform:uppercase;font-weight:700;opacity:.8">Mesa</span>
          <span style="font-size:24px;font-weight:900">${table.table_number ?? '?'}</span>
          <span style="font-size:9px;margin-top:2px;opacity:.9;font-weight:600">${s.label}</span>
        </button>
      `;
    }).join('');

    console.log("¡Inyección de HTML de mesas completada en el contenedor!");
  }

  _isMerging() {
    return this.selectedTablesForMerge.length > 0;
  }

  selectTable(id) {
    if (this._isMerging()) {
      this._toggleTableSelection(id);
      return;
    }

    this.activeTable = id;
    const table = this.tables.find(t => t.id === id);

    if (this.activeTableBadge) this.activeTableBadge.innerText = `MESA ${table?.table_number || id}`;
    if (this.activeOrderTitle) {
      this.activeOrderTitle.innerText = table?.status === 'occupied' ? 'Añadir al Pedido' : 'Nueva Comanda';
    }
    if (this.btnSendKitchen) this.btnSendKitchen.disabled = false;
    if (this.btnCancelOrder) this.btnCancelOrder.disabled = false;

    this.renderTables();
    this.renderActiveOrder();
    this.renderMenuItems();
  }

  _toggleTableSelection(tableId) {
    const table = this.tables.find(t => t.id === tableId);
    if (table?.status === 'occupied') return;

    const idx = this.selectedTablesForMerge.indexOf(tableId);
    if (idx === -1) {
      this.selectedTablesForMerge.push(tableId);
    } else {
      this.selectedTablesForMerge.splice(idx, 1);
    }

    this.renderTables();
  }

  _showMergeModal() {
    if (this.selectedTablesForMerge.length < 2) {
      if (this.activeTable) {
        const activeT = this.tables.find(t => t.id === this.activeTable);
        if (activeT && activeT.status !== 'occupied' && activeT.status !== 'maintenance') {
          this.selectedTablesForMerge = [this.activeTable];
          this.renderTables();
          this.toast.show(`Modo fusión: seleccione otras mesas libres para juntar con la Mesa ${activeT.table_number}`, 'info');
          return;
        }
      }
      this.toast.show('Seleccione una mesa libre primero para iniciar la fusión', 'warning');
      return;
    }

    const selectedTableNumbers = this.selectedTablesForMerge.map(id => {
      const table = this.tables.find(t => t.id === id);
      return table?.table_number;
    }).join(', ');

    const content = `
      <div class="flex justify-between items-center mb-6">
        <h3 class="font-headline text-xl font-bold">Fusionar Mesas</h3>
        <button onclick="app.viewManager.hideModal()" class="text-on-surface-variant hover:text-on-surface">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>
      <p class="text-on-surface-variant mb-4">¿Fusionar las mesas ${selectedTableNumbers}?</p>
      <p class="text-sm text-on-surface-variant mb-6">Todos los pedidos se agruparán en una sola cuenta.</p>
      <div class="flex gap-3">
        <button onclick="app.waiterView.cancelMerge()" class="flex-1 bg-surface-container text-on-surface py-3 font-bold rounded-xl">Cancelar</button>
        <button onclick="app.waiterView.executeMerge()" class="flex-1 btn-gradient py-3 text-on-primary font-bold rounded-xl">Fusionar</button>
      </div>
    `;

    app.viewManager.showModal(content);
  }

  async executeMerge() {
    try {
      if (this.selectedTablesForMerge.length < 2) {
        this.toast.show('Selecciona al menos 2 mesas para fusionar', 'warning');
        return;
      }

      const payload = {
        main_table_id: this.selectedTablesForMerge[0],
        tables_to_merge: this.selectedTablesForMerge.slice(1)
      };

      // Se pasa un único objeto que el ApiClient/Fetch pueda serializar como JSON
      const result = await this.api.mergeTables(payload);

      if (result.success) {
        app.viewManager.hideModal();
        this.toast.show('Mesas fusionadas con éxito', 'success');
        this.selectedTablesForMerge = [];
        await this.loadData();
      } else {
        this.toast.show(result.message || 'Error al fusionar', 'error');
      }
    } catch (error) {
      console.error('Merge error:', error);
      this.toast.show('Error de red al fusionar', 'error');
    }
  }

  async handleCreateTable() {
    try {
      const res = await this.api.createTable();
      if(res.success) {
        this.toast.show(res.message, 'success');
        await this.loadData();
      } else {
        this.toast.show(res.error || 'Error', 'error');
      }
    } catch(e) {
      this.toast.show('Error de red', 'error');
    }
  }

  cancelMerge() {
    this.selectedTablesForMerge = [];
    this.renderTables();
    app.viewManager.hideModal();
  }

  renderCategoryChips() {
    if (!this.categoryChips) return;

    this.categoryChips.innerHTML = this.categories.map(cat => `
      <button onclick="app.waiterView.setActiveCategory('${cat}')"
              class="px-5 py-2 rounded-lg text-sm font-semibold transition-colors ${cat === 'Plato Especial' ? 'bg-red-600 text-white' : 'bg-surface-container-highest text-on-surface'}">
        ${cat}
      </button>
    `).join('');
  }

  setActiveCategory(cat) {
    this.activeCategory = cat;
    this.renderCategoryChips();
    this.renderMenuItems();
  }

  checkStockAvailability(productId) {
    const recipe = this.recipes.find(r => r.product_id === productId);
    if (!recipe || !recipe.ingredients) return true;

    return recipe.ingredients.every(ri => {
      if (!ri || !ri.ingredient_id) return false;
      const ing = this.inventoryMap[ri.ingredient_id];
      // Corrección de propiedad: ri.quantity en vez de ri.quantity_required
      return ing && Number(ing.stock) >= Number(ri.quantity);
    });
  }

  renderMenuItems() {
    if (!this.menuItems) return;

    if (!this.activeTable) {
      this.menuItems.innerHTML = `<div class="col-span-full py-12 text-center text-on-surface-variant font-medium">Selecciona una mesa para ver el menú</div>`;
      if (this.btnSendKitchen) this.btnSendKitchen.disabled = true;
      if (this.btnCancelOrder) this.btnCancelOrder.disabled = true;
      return;
    }

    // UUID exclusivo mapeado en la relación de categorías de Supabase
    const SPECIAL_CATEGORY_UUID = '99999999-9999-9999-9999-999999999999';
    let filtered = [];

    // LÓGICA DE FILTRADO CORREGIDA
    if (this.activeCategory === 'Todo' || this.activeCategory === 'All') {
      // "Todo" muestra todos los productos MENOS los que tengan el UUID del plato especial
      filtered = this.products.filter(p => p.category_id !== SPECIAL_CATEGORY_UUID);
    } else if (this.activeCategory === 'Plato Especial') {
      // La pestaña "Plato Especial" muestra ÚNICAMENTE los platos vinculados a ese UUID
      filtered = this.products.filter(p => p.category_id === SPECIAL_CATEGORY_UUID);
    } else {
      // Las demás categorías filtran por su nombre, garantizando que NO se cuele el plato especial
      filtered = this.products.filter(p => 
        (p.category === this.activeCategory || p.category_name === this.activeCategory) && 
        p.category_id !== SPECIAL_CATEGORY_UUID
      );
    }

    this.menuItems.innerHTML = filtered.map(product => {
      const isAvailable = this.checkStockAvailability(product.id);
      return `
        <button ${!isAvailable ? 'disabled' : ''} data-product-id="${product.id}" class="product-card group bg-surface-container-lowest p-5 rounded-xl border border-surface-container-highest hover:border-primary/50 transition-all text-left flex flex-col justify-between h-32 ${!isAvailable ? 'opacity-40 grayscale cursor-not-allowed' : 'active:scale-95 hover:shadow-md'}">
          <div>
            <span class="text-[10px] text-primary font-bold uppercase tracking-widest">
              ${this.activeCategory === 'Todo' || this.activeCategory === 'All' ? 'Plato' : this.activeCategory}
            </span>
            <h4 class="font-headline font-bold text-on-surface leading-tight mt-1">${product.name}</h4>
          </div>
          <div class="flex justify-between items-end w-full">
            <span class="font-body font-bold text-on-surface-variant">${product.formattedPrice || this.formatCurrency(product.price)}</span>
            ${isAvailable
          ? `<i data-lucide="plus-circle" class="w-5 h-5 text-primary opacity-0 group-hover:opacity-100 transition-opacity"></i>`
          : `<span class="text-[10px] text-red-600 font-bold uppercase">Sin Stock</span>`}
          </div>
        </button>
      `;
    }).join('');
    lucide.createIcons();
  }

  addToOrder(productId) {
    if (!this.activeTable) return;

    const product = this.products.find(p => p.id === productId);
    if (!product) return;

    if (!this.checkStockAvailability(productId)) {
      this.toast.show('Stock insuficiente en bodega', 'error');
      return;
    }

    const existing = this.pendingItems.find(i => i.product_id === productId);
    if (existing) {
      existing.quantity++;
      existing.subtotal = existing.quantity * existing.unit_price;
    } else {
      this.pendingItems.push({
        product_id: product.id,
        name: product.name,
        unit_price: product.price,
        quantity: 1,
        subtotal: product.price
      });
    }

    this.renderActiveOrder();
    this.renderTables();
  }

  removeFromOrder(productId) {
    const itemIndex = this.pendingItems.findIndex(i => i.product_id === productId);
    if (itemIndex === -1) return;

    if (this.pendingItems[itemIndex].quantity > 1) {
      this.pendingItems[itemIndex].quantity--;
      this.pendingItems[itemIndex].subtotal = this.pendingItems[itemIndex].quantity * this.pendingItems[itemIndex].unit_price;
    } else {
      this.pendingItems.splice(itemIndex, 1);
    }

    if (this.pendingItems.length === 0) {
      if (this.activeOrderTitle) this.activeOrderTitle.innerText = 'Nueva Comanda';
    }

    this.renderActiveOrder();
    this.renderTables();
  }

  renderActiveOrder() {
    if (!this.orderItemsList || !this.orderSubtotal || !this.orderTotal) return;

    if (!this.activeTable || this.pendingItems.length === 0) {
      this.orderItemsList.innerHTML = `
        <div class="flex flex-col items-center justify-center h-full text-on-surface-variant opacity-50 space-y-2">
          <i data-lucide="shopping-basket" class="w-8 h-8"></i>
          <p class="text-sm font-medium">Comanda vacía</p>
        </div>`;
      this.orderSubtotal.innerText = '$0';
      this.orderTotal.innerText = '$0';
      lucide.createIcons();
      return;
    }

    let total = 0;

    this.orderItemsList.innerHTML = this.pendingItems.map(item => {
      total += item.subtotal
      return `
        <div class="flex justify-between items-center p-3 bg-surface-container rounded-lg border border-transparent hover:border-surface-container-highest transition-colors">
          <div class="flex items-center gap-3">
            <span class="bg-surface-container-highest text-on-surface w-6 h-6 flex items-center justify-center rounded text-[10px] font-bold">${item.quantity}x</span>
            <span class="text-sm font-semibold text-on-surface">${item.name}</span>
          </div>
          <div class="flex items-center gap-3">
            <span class="text-sm font-headline font-bold text-on-surface-variant">${this.formatCurrency(item.subtotal)}</span>
            <button onclick="app.waiterView.removeFromOrder('${item.product_id}')" class="text-on-surface-variant hover:text-red-600 bg-surface-container-highest hover:bg-red-100 p-1.5 rounded transition-colors">
              <i data-lucide="minus" class="w-3.5 h-3.5"></i>
            </button>
          </div>
        </div>
      `;
    }).join('');

    this.orderSubtotal.innerText = this.formatCurrency(total);
    this.orderTotal.innerText = this.formatCurrency(total);
    lucide.createIcons();
  }

  async _sendToKitchen() {
    if (!this.activeTable || this.pendingItems.length === 0) return;

    try {
      const orderResult = await this.api.createOrder({
        table_id: this.activeTable,
        items: this.pendingItems,
        user_id: app.currentUser?.id
      });

      if (orderResult.success) {
        const sendResult = await this.api.sendToKitchen(orderResult.data.id);

        if (sendResult.success) {
          const table = this.tables.find(t => t.id === this.activeTable);
          this.toast.show(`Comanda Mesa ${table?.table_number || this.activeTable} enviada a cocina`);

          this.pendingItems = [];
          this.activeTable = null;

          if (this.activeTableBadge) this.activeTableBadge.innerText = '--';
          if (this.activeOrderTitle) this.activeOrderTitle.innerText = 'Selecciona Mesa';

          await this.loadData();
          this.renderActiveOrder();
        } else {
          this.toast.show(sendResult.error || 'Error al enviar a cocina', 'error');
        }
      } else {
        // Interceptar error de stock real (quiebre de stock)
        if (orderResult.missing && orderResult.missing.length > 0) {
          let missingHtml = '¡Quiebre de stock real!<br/>';
          orderResult.missing.forEach(m => {
            missingHtml += `• ${m.name}: Requerido ${m.required}, Disponible ${m.available}<br/>`;
          });
          this.toast.show(missingHtml, 'error');
        } else {
          this.toast.show(orderResult.error || 'Error al crear comanda', 'error');
        }
      }
    } catch (error) {
      this.toast.show('Error al enviar a cocina', 'error');
    }
  }

  async _cancelOrder() {
    if (!this.activeTable || this.pendingItems.length === 0) return;

    this.pendingItems = [];
    this.activeTable = null;

    if (this.activeOrderTitle) this.activeOrderTitle.innerText = 'Nueva Comanda';
    if (this.activeTableBadge) this.activeTableBadge.innerText = '--';

    await this.loadData();
    this.renderActiveOrder();
    this.toast.show('Comanda cancelada');
  }

  handleSelectProduct(productId) {
    console.log("Plato seleccionado ID:", productId);
    if (typeof this.addToOrder === 'function') {
      this.addToOrder(productId);
    } else {
      this.toast.show('Método addToOrder no encontrado', 'error');
    }
  }

  formatCurrency(amount) {
    return new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(amount);
  }

  // Controla la visibilidad del menú desplegable de platos especiales
  toggleSpecialMenu(event) {
    event.stopPropagation();
    const dropdown = document.getElementById('special-menu-options');
    if (!dropdown) return;
    
    dropdown.classList.toggle('hidden');
    
    // Cerrar el menú si se hace clic en cualquier otro lugar de la pantalla
    const closeMenu = (e) => {
      if (!dropdown.contains(e.target) && e.target !== event.target) {
        dropdown.classList.add('hidden');
        document.removeEventListener('click', closeMenu);
      }
    };
    document.addEventListener('click', closeMenu);
  }

  // Busca el plato por nombre dentro de la lista cargada de productos y lo añade a la comanda
  selectSpecialPlate(plateName) {
    // Ocultar el menú al seleccionar una opción
    const dropdown = document.getElementById('special-menu-options');
    if (dropdown) dropdown.classList.add('hidden');

    if (!this.activeTable) {
      this.toast.show('Selecciona una mesa primero antes de añadir productos', 'warning');
      return;
    }

    // Buscar el producto en el array local usando su nombre
    const targetProduct = this.products.find(p => p.name.toLowerCase() === plateName.toLowerCase());

    if (targetProduct) {
      this.addToOrder(targetProduct.id);
    } else {
      this.toast.show(`El plato "${plateName}" no está registrado en el sistema`, 'error');
    }
  }
}

window.WaiterView = WaiterView;