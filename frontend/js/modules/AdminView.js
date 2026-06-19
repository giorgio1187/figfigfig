class AdminView {
  constructor(api, toast) {
    this.api = api;
    this.toast = toast;
    this.inventory = [];
    this.users = [];
    this.recipes = [];
    this.products = [];
    this.pendingPayments = [];
    this.stats = null;
    this.weeklySales = null;

    this.inventoryTableBody = document.getElementById('inventory-table-body');
    this.usersTableBody = document.getElementById('users-table-body');
    this.recipesTableBody = document.getElementById('recipes-table-body');
    this.totalRevenue = document.getElementById('total-revenue');
    this.adminOrdersCount = document.getElementById('admin-orders-count');
    this.adminActiveUsers = document.getElementById('admin-active-users');
    this.adminLowStock = document.getElementById('admin-low-stock');
    this.paymentPendingList = document.getElementById('payment-pending-list');
    this.weeklyTotalRevenue = document.getElementById('weekly-total-revenue');
    this.weeklyOrderCount = document.getElementById('weekly-order-count');
    this.weeklyDateRange = document.getElementById('weekly-date-range');
    this.weeklyDailyBreakdown = document.getElementById('weekly-daily-breakdown');
    this.btnAddUser = document.getElementById('btn-add-user');
    this.btnAddRecipe = document.getElementById('btn-add-recipe');

    this._bindEvents();
  }

  _bindEvents() {
    if (this.btnAddUser) {
      this.btnAddUser.addEventListener('click', () => this._showAddUserModal());
    }
    if (this.btnAddRecipe) {
      this.btnAddRecipe.addEventListener('click', () => this._showAddRecipeModal());
    }
  }

  async loadData() {
    try {
      const [inventoryRes, usersRes, pendingRes, statsRes, recipesRes, productsRes, weeklyRes] = await Promise.all([
        this.api.getInventory(),
        this.api.getUsers(),
        this.api.getPendingPaymentOrders(),
        this.api.getStats(),
        this.api.getRecipes(),
        this.api.getProducts(true),
        this.api.getWeeklyRevenue()
      ]);

      if (inventoryRes.success) this.inventory = inventoryRes.data;
      if (usersRes.success) this.users = usersRes.data;
      if (recipesRes.success) this.recipes = recipesRes.data;
      if (productsRes.success) this.products = productsRes.data;
      if (pendingRes.success) this.pendingPayments = pendingRes.data;
      if (statsRes.success) this.stats = statsRes.data;
      if (weeklyRes.success) this.weeklySales = weeklyRes.data;

      this.renderInventory();
      this.renderUsers();
      this.renderRecipes();
      this.renderDashboard();
      this.renderWeeklySales();
      this.renderPendingPayments();
    } catch (error) {
      console.error('Load admin data error:', error);
      this.toast.show('Error al cargar datos', 'error');
    }
  }

  renderInventory() {
    if (!this.inventoryTableBody) return;

    this.inventoryTableBody.innerHTML = this.inventory.map(item => {
      const isLow = item.stock < item.low_stock_threshold;
      return `
        <tr class="hover:bg-surface-container transition-colors">
          <td class="p-4 font-semibold text-on-surface">${item.name}</td>
          <td class="p-4">
            <span class="px-2 py-1 rounded-full text-[10px] font-bold ${isLow ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'}">
              ${isLow ? 'Crítico' : 'Óptimo'}
            </span>
          </td>
          <td class="p-4 text-right font-headline font-bold text-on-surface-variant">${item.stock} ${item.unit}</td>
          <td class="p-4 text-right">
            <button onclick="app.adminView.restockIngredient('${item.id}')" class="text-tertiary hover:text-on-surface font-bold text-xs px-3 py-1 bg-surface-container rounded hover:bg-surface-container-highest transition-colors">
              + Añadir 10
            </button>
          </td>
        </tr>
      `;
    }).join('');
  }

  renderUsers() {
    if (!this.usersTableBody) return;

    const roleLabels = { admin: 'Admin', waiter: 'Garzón', chef: 'Chef' };

    this.usersTableBody.innerHTML = this.users.map(user => `
      <tr class="hover:bg-surface-container transition-colors">
        <td class="p-4">
          <div class="font-semibold text-on-surface">${user.name}</div>
          <div class="text-xs text-on-surface-variant">${user.username}</div>
        </td>
        <td class="p-4">
          <span class="px-2 py-1 rounded-full text-[10px] font-bold bg-surface-container text-on-surface-variant">
            ${roleLabels[user.role] || user.role}
          </span>
        </td>
        <td class="p-4">
          <span class="px-2 py-1 rounded-full text-[10px] font-bold ${user.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600'}">
            ${user.is_active ? 'Activo' : 'Inactivo'}
          </span>
        </td>
        <td class="p-4 text-right">
          <div class="flex justify-end gap-2">
            <button onclick="app.adminView._showEditUserModal('${user.id}')" class="text-tertiary hover:text-on-surface font-bold text-xs px-3 py-1 bg-surface-container rounded hover:bg-surface-container-highest transition-colors">
              Editar
            </button>
            <button onclick="app.adminView.toggleUserStatus('${user.id}')" class="${user.is_active ? 'text-red-600' : 'text-green-600'} hover:text-on-surface font-bold text-xs px-3 py-1 bg-surface-container rounded hover:bg-surface-container-highest transition-colors">
              ${user.is_active ? 'Desactivar' : 'Activar'}
            </button>
            ${user.username !== 'admin' ? `
              <button onclick="app.adminView.deleteUser('${user.id}')" class="text-red-600 hover:text-red-800 font-bold text-xs px-3 py-1 bg-surface-container rounded hover:bg-red-100 transition-colors">Eliminar</button>
            ` : ''}
          </div>
        </td>
      </tr>
    `).join('');
  }

  renderRecipes() {
    if (!this.recipesTableBody) return;

    const productNames = this.products.reduce((map, product) => {
      map[product.id] = product.name;
      return map;
    }, {});

    this.recipesTableBody.innerHTML = this.recipes.map(recipe => {
      const productName = productNames[recipe.product_id] || 'Producto desconocido';
      const ingredientsList = recipe.ingredients || recipe.recipe_ingredients || recipe.RecipeIngredients || [];
      const ingredientCount = ingredientsList.length;

      return `
      <tr class="hover:bg-surface-container transition-colors">
        <td class="p-4 font-semibold text-on-surface">${productName}</td>
        <td class="p-4 text-on-surface-variant">
          ${ingredientCount} ${ingredientCount === 1 ? 'ingrediente' : 'ingredientes'}
        </td>
        <td class="p-4 text-right">
          <button onclick="app.adminView._showEditRecipeModal('${recipe.id}')" class="text-tertiary hover:text-on-surface font-bold text-xs px-3 py-1 bg-surface-container rounded hover:bg-surface-container-highest transition-colors">
            Editar
          </button>
        </td>
      </tr>
    `;
    }).join('');
  }

  _getProductName(productId) {
    const product = this.products.find(p => p.id === productId);
    return product ? product.name : 'Producto desconocido';
  }

  async _showAddRecipeModal() {
    const productOptions = this.products.map(product => `
      <option value="${product.id}">${product.name}</option>
    `).join('');

    const ingredientRows = this.inventory.map(item => `
      <div class="flex items-center gap-3 mb-3">
        <label class="flex items-center gap-2 flex-1">
          <input type="checkbox" name="recipe-ingredient" value="${item.id}" class="form-checkbox h-4 w-4 text-primary rounded focus:ring-primary/50">
          <span class="text-sm">${item.name}</span>
        </label>
        <input type="number" id="ingredient-qty-${item.id}" min="0" step="any" value="0" class="w-24 px-3 py-2 rounded-xl bg-surface-container-low border border-surface-container focus:outline-none focus:border-primary/50" placeholder="Cantidad">
      </div>
    `).join('');

    const content = `
      <div class="flex justify-between items-center mb-6">
        <h3 class="font-headline text-xl font-bold">Nueva Receta</h3>
        <button onclick="app.viewManager.hideModal()" class="text-on-surface-variant hover:text-on-surface">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>
      <form id="add-recipe-form" class="space-y-4">
        <div>
          <label class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-1">Producto</label>
          <select id="new-recipe-product" class="w-full px-4 py-3 rounded-xl bg-surface-container-low border border-surface-container focus:outline-none focus:border-primary/50 font-medium">
            ${productOptions}
          </select>
        </div>
        <div>
          <div class="flex justify-between items-center mb-1">
            <label class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant">Ingredientes</label>
            <button type="button" onclick="app.adminView._showAddIngredientModal(true)" class="text-primary text-xs font-bold hover:underline">+ Agregar ingrediente</button>
          </div>
          <div class="max-h-64 overflow-y-auto pr-2">
            ${ingredientRows}
          </div>
        </div>
        <button type="submit" class="w-full btn-gradient py-4 text-on-primary font-bold rounded-xl shadow-lg active:scale-95 transition-transform flex justify-center items-center gap-2 mt-2">
          <i data-lucide="plus-square" class="w-5 h-5"></i>
          CREAR RECETA
        </button>
      </form>
    `;

    app.viewManager.showModal(content);

    document.getElementById('add-recipe-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const productId = document.getElementById('new-recipe-product').value;

      try {
        // 1. Inicializamos la receta base vinculada al producto
        const recipeResult = await this.api.createRecipe(productId);

        if (recipeResult.success) {
          const recipeId = recipeResult.data.id;
          
          // 2. Buscamos todos los inputs numéricos de la vista
          const allQuantityInputs = Array.from(document.querySelectorAll('input[id^="ingredient-qty-"]'));

          for (const input of allQuantityInputs) {
            const ingredientId = input.id.replace('ingredient-qty-', '');
            const quantity = parseFloat(input.value) || 0;
            const checkbox = document.querySelector(`input[name="recipe-ingredient"][value="${ingredientId}"]`);
            
            // Si tiene cantidad asignada o el checkbox fue marcado, se asocia con su respectivo valor
            if (quantity > 0 || (checkbox && checkbox.checked)) {
              await this.api.updateRecipeIngredient(recipeId, ingredientId, quantity);
            }
          }

          this.toast.show('Receta creada correctamente');
          app.viewManager.hideModal();
          await this.loadData();
        } else {
          this.toast.show(recipeResult.message || 'Error al guardar la receta', 'error');
        }
      } catch (error) {
        console.error('Create recipe error:', error);
        this.toast.show('Error al procesar los ingredientes de la receta', 'error');
      }
    });
  }

  async _showAddIngredientModal(reopenRecipe = false, recipeId = null) {
    const content = `
      <div class="flex justify-between items-center mb-6">
        <h3 class="font-headline text-xl font-bold">Nuevo Ingrdiente</h3>
        <button onclick="app.viewManager.hideModal()" class="text-on-surface-variant hover:text-on-surface">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>
      <form id="add-ingredient-form" class="space-y-4">
        <div>
          <label class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-1">Nombre</label>
          <input type="text" id="new-ingredient-name" class="w-full px-4 py-3 rounded-xl bg-surface-container-low border border-surface-container focus:outline-none focus:border-primary/50 font-medium" required>
        </div>
        <div>
          <label class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-1">Unidad</label>
          <input type="text" id="new-ingredient-unit" class="w-full px-4 py-3 rounded-xl bg-surface-container-low border border-surface-container focus:outline-none focus:border-primary/50 font-medium" placeholder="Ej. kg, unidad" required>
        </div>
        <div>
          <label class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-1">Stock inicial</label>
          <input type="number" id="new-ingredient-stock" min="0" value="0" class="w-full px-4 py-3 rounded-xl bg-surface-container-low border border-surface-container focus:outline-none focus:border-primary/50 font-medium">
        </div>
        <div>
          <label class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-1">Umbral bajo stock</label>
          <input type="number" id="new-ingredient-threshold" min="0" value="5" class="w-full px-4 py-3 rounded-xl bg-surface-container-low border border-surface-container focus:outline-none focus:border-primary/50 font-medium">
        </div>
        <button type="submit" class="w-full btn-gradient py-4 text-on-primary font-bold rounded-xl shadow-lg active:scale-95 transition-transform flex justify-center items-center gap-2 mt-2">
          <i data-lucide="plus-square" class="w-5 h-5"></i>
          AGREGAR INGREDIENTE
        </button>
      </form>
    `;

    app.viewManager.showModal(content);

    document.getElementById('add-ingredient-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const name = document.getElementById('new-ingredient-name').value.trim();
      const unit = document.getElementById('new-ingredient-unit').value.trim();
      const stock = parseFloat(document.getElementById('new-ingredient-stock').value) || 0;
      const low_stock_threshold = parseFloat(document.getElementById('new-ingredient-threshold').value) || 0;

      try {
        const result = await this.api.addIngredient({ name, unit, stock, low_stock_threshold });
        if (!result.success) {
          this.toast.show(result.error || 'Error al crear ingrediente', 'error');
          return;
        }

        this.toast.show('Ingrediente agregado correctamente');
        app.viewManager.hideModal();
        await this.loadData();

        if (reopenRecipe) {
          if (recipeId) {
            this._showEditRecipeModal(recipeId);
          } else {
            this._showAddRecipeModal();
          }
        }
      } catch (error) {
        console.error('Add ingredient error:', error);
        this.toast.show('Error al crear ingrediente', 'error');
      }
    });
  }

  async _showEditRecipeModal(recipeId) {
    const recipe = this.recipes.find(r => r.id === recipeId);
    if (!recipe) return;

    const targetProductId = recipe.product_id;
    const rawIngredients = recipe.ingredients || recipe.recipe_ingredients || recipe.RecipeIngredients || [];

    const ingredientMap = rawIngredients.reduce((map, ing) => {
      map[ing.ingredient_id] = ing;
      return map;
    }, {});

    const ingredientRows = this.inventory.map(item => {
      const existing = ingredientMap[item.id];
      const currentQty = existing ? (existing.quantity_required ?? existing.quantity ?? 0) : 0;
      return `
      <div class="flex items-center gap-3 mb-3">
        <label class="flex items-center gap-2 flex-1">
          <input type="checkbox" name="recipe-ingredient" value="${item.id}" ${existing ? 'checked' : ''} class="form-checkbox h-4 w-4 text-primary rounded focus:ring-primary/50">
          <span class="text-sm">${item.name}</span>
        </label>
        <input type="number" id="ingredient-qty-${item.id}" min="0" step="any" value="${currentQty}" class="w-24 px-3 py-2 rounded-xl bg-surface-container-low border border-surface-container focus:outline-none focus:border-primary/50" placeholder="Cantidad">
      </div>
    `;
    }).join('');

    const content = `
      <div class="flex justify-between items-center mb-6">
        <h3 class="font-headline text-xl font-bold">Editar Receta</h3>
        <button onclick="app.viewManager.hideModal()" class="text-on-surface-variant hover:text-on-surface">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>
      <form id="edit-recipe-form" class="space-y-4">
        <div>
          <label class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-1">Producto</label>
          <div class="px-4 py-3 rounded-xl bg-surface-container border border-surface-container text-on-surface">${this._getProductName(targetProductId)}</div>
        </div>
        <div>
          <div class="flex justify-between items-center mb-1">
            <label class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant">Ingredientes asociados</label>
            <button type="button" onclick="app.adminView._showAddIngredientModal(true, '${recipeId}')" class="text-primary text-xs font-bold hover:underline">+ Agregar ingrediente</button>
          </div>
          <div class="max-h-64 overflow-y-auto pr-2">
            ${ingredientRows}
          </div>
        </div>
        <button type="submit" class="w-full btn-gradient py-4 text-on-primary font-bold rounded-xl shadow-lg active:scale-95 transition-transform flex justify-center items-center gap-2 mt-2">
          <i data-lucide="save" class="w-5 h-5"></i>
          GUARDAR RECETA
        </button>
      </form>
    `;

    app.viewManager.showModal(content);

    document.getElementById('edit-recipe-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      
      // Obtenemos todos los inputs numéricos de ingredientes
      const allQuantityInputs = Array.from(document.querySelectorAll('input[id^="ingredient-qty-"]'));
      
      try {
        for (const input of allQuantityInputs) {
          const ingredientId = input.id.replace('ingredient-qty-', '');
          const quantity = parseFloat(input.value) || 0;
          const checkbox = document.querySelector(`input[name="recipe-ingredient"][value="${ingredientId}"]`);
          
          const isChecked = checkbox ? checkbox.checked : false;
          const wasAssociated = !!ingredientMap[ingredientId];

          // Sincronización inteligente basada en la cantidad numérica
          if (quantity > 0 || isChecked) {
            await this.api.updateRecipeIngredient(recipeId, ingredientId, quantity);
          } else if (quantity === 0 && !isChecked && wasAssociated) {
            await this.api.removeRecipeIngredient(recipeId, ingredientId);
          }
        }

        this.toast.show('Receta actualizada correctamente');
        app.viewManager.hideModal();
        await this.loadData();
      } catch (error) {
        console.error('Update recipe error:', error);
        this.toast.show('Error al actualizar los componentes de la receta', 'error');
      }
    });
  }

  renderDashboard() {
    if (!this.stats) return;

    if (this.totalRevenue) this.totalRevenue.innerText = this.stats.orders?.formatted_revenue || '$0';
    if (this.adminOrdersCount) this.adminOrdersCount.innerText = this.stats.orders?.completed || 0;
    if (this.adminActiveUsers) this.adminActiveUsers.innerText = this.stats.users?.active || 0;
    if (this.adminLowStock) this.adminLowStock.innerText = this.stats.ingredients?.low_stock || 0;
  }

  renderWeeklySales() {
    if (!this.weeklySales) return;

    const { week_start, week_end, formatted_revenue, order_count, days } = this.weeklySales;

    if (this.weeklyTotalRevenue) {
      this.weeklyTotalRevenue.innerText = formatted_revenue || this.formatCurrency(0);
    }
    if (this.weeklyOrderCount) {
      this.weeklyOrderCount.innerText = order_count ?? 0;
    }
    if (this.weeklyDateRange && week_start && week_end) {
      this.weeklyDateRange.innerText = `${this._formatShortDate(week_start)} – ${this._formatShortDate(week_end)}`;
    }
    if (!this.weeklyDailyBreakdown || !days?.length) return;

    const maxRevenue = Math.max(...days.map(d => d.revenue), 1);

    this.weeklyDailyBreakdown.innerHTML = days.map(day => {
      const barWidth = day.revenue > 0 ? Math.round((day.revenue / maxRevenue) * 100) : 0;
      return `
        <div class="flex items-center gap-3 text-sm">
          <span class="w-8 font-bold text-on-surface-variant">${day.day_label}</span>
          <div class="flex-1 h-7 bg-surface-container rounded-lg overflow-hidden relative">
            <div class="h-full bg-tertiary/80 rounded-lg transition-all" style="width: ${barWidth}%"></div>
          </div>
          <span class="w-24 text-right font-bold text-on-surface">${day.formatted_revenue || this.formatCurrency(day.revenue)}</span>
          <span class="w-12 text-right text-on-surface-variant text-xs">${day.order_count} ped.</span>
        </div>
      `;
    }).join('');
  }

  _formatShortDate(dateStr) {
    const date = new Date(`${dateStr}T12:00:00`);
    return date.toLocaleDateString('es-CL', { day: 'numeric', month: 'short' });
  }

  renderPendingPayments() {
    if (!this.paymentPendingList) return;

    if (this.pendingPayments.length === 0) {
      this.paymentPendingList.innerHTML = `<p class="text-center text-on-surface-variant py-8 text-sm">No hay cuentas pendientes.</p>`;
      return;
    }

    this.paymentPendingList.innerHTML = this.pendingPayments.map(order => {
      const orderTotal = order.total;
      return `
        <div class="p-4 rounded-xl bg-surface-container flex justify-between items-center group border border-surface-container-highest">
          <div>
            <div class="text-[10px] font-bold uppercase text-on-surface-variant">Ticket Mesa ${order.table?.table_number || order.table_number || order.table_id}</div>
            <div class="text-lg font-headline font-bold text-on-surface">${this.formatCurrency(orderTotal)}</div>
          </div>
          <button onclick="app.adminView.processPayment('${order.id}')" class="bg-surface-container-highest group-hover:bg-primary group-hover:text-on-primary px-4 py-2 rounded-lg text-xs font-bold transition-all flex items-center gap-2">
            <i data-lucide="wallet" class="w-4 h-4"></i> PAGAR
          </button>
        </div>
      `;
    }).join('');

    lucide.createIcons();
  }

  async restockIngredient(id) {
    try {
      const result = await this.api.restockIngredient(id, 10);
      if (result.success) {
        this.toast.show(`Bodega actualizada: ${result.data.name}`);
        await this.loadData();
      } else {
        this.toast.show(result.error || 'Error al reabastecer', 'error');
      }
    } catch (error) {
      this.toast.show('Error al reabastecer', 'error');
    }
  }

  async processPayment(orderId) {
    try {
      const result = await this.api.processPayment(orderId);
      if (result.success) {
        this.toast.show('Pago procesado. Mesa liberada.');
        await this.loadData();
      } else {
        this.toast.show(result.error || 'Error al procesar pago', 'error');
      }
    } catch (error) {
      this.toast.show('Error al procesar pago', 'error');
    }
  }

  async toggleUserStatus(userId) {
    try {
      const result = await this.api.toggleUserStatus(userId);
      if (result.success) {
        const action = result.data.is_active ? 'activado' : 'desactivado';
        this.toast.show(`Usuario ${result.data.name} ${action}`);
        await this.loadData();
      } else {
        this.toast.show(result.error || 'Error al cambiar estado', 'error');
      }
    } catch (error) {
      this.toast.show('Error al cambiar estado', 'error');
    }
  }

  async deleteUser(userId) {
    if (!confirm('¿Eliminar este usuario?')) return;

    try {
      const result = await this.api.deleteUser(userId);
      if (result.success) {
        this.toast.show('Usuario eliminado');
        await this.loadData();
      } else {
        this.toast.show(result.error || 'Error al eliminar usuario', 'error');
      }
    } catch (error) {
      this.toast.show('Error al eliminar usuario', 'error');
    }
  }

  _showAddUserModal() {
    const content = `
      <div class="flex justify-between items-center mb-6">
        <h3 class="font-headline text-xl font-bold">Nuevo Usuario</h3>
        <button onclick="app.viewManager.hideModal()" class="text-on-surface-variant hover:text-on-surface">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>
      <form id="add-user-form" class="space-y-4">
        <div>
          <label class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-1">Nombre</label>
          <input type="text" id="new-user-name" class="w-full px-4 py-3 rounded-xl bg-surface-container-low border border-surface-container focus:outline-none focus:border-primary/50 font-medium" required>
        </div>
        <div>
          <label class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-1">Usuario</label>
          <input type="text" id="new-user-username" class="w-full px-4 py-3 rounded-xl bg-surface-container-low border border-surface-container focus:outline-none focus:border-primary/50 font-medium" required>
        </div>
        <div>
          <label class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-1">Contraseña</label>
          <input type="password" id="new-user-password" class="w-full px-4 py-3 rounded-xl bg-surface-container-low border border-surface-container focus:outline-none focus:border-primary/50 font-medium" required minlength="6">
        </div>
        <div>
          <label class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-1">Rol</label>
          <select id="new-user-role" class="w-full px-4 py-3 rounded-xl bg-surface-container-low border border-surface-container focus:outline-none focus:border-primary/50 font-medium">
            <option value="waiter">Garzón</option>
            <option value="chef">Chef</option>
            <option value="admin">Admin</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-1">Estación</label>
          <input type="text" id="new-user-station" class="w-full px-4 py-3 rounded-xl bg-surface-container-low border border-surface-container focus:outline-none focus:border-primary/50 font-medium">
        </div>
        <button type="submit" class="w-full btn-gradient py-4 text-on-primary font-bold rounded-xl shadow-lg active:scale-95 transition-transform flex justify-center items-center gap-2 mt-2">
          <i data-lucide="user-plus" class="w-5 h-5"></i>
          CREAR USUARIO
        </button>
      </form>
    `;

    app.viewManager.showModal(content);

    document.getElementById('add-user-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const userData = {
        name: document.getElementById('new-user-name').value,
        username: document.getElementById('new-user-username').value,
        password: document.getElementById('new-user-password').value,
        role: document.getElementById('new-user-role').value,
        station: document.getElementById('new-user-station').value
      };

      try {
        const result = await this.api.addUser(userData);
        if (result.success) {
          this.toast.show(`Usuario ${result.data.name} creado`);
          app.viewManager.hideModal();
          await this.loadData();
        } else {
          this.toast.show(result.error || result.errors?.join(', ') || 'Error al crear usuario', 'error');
        }
      } catch (error) {
        this.toast.show('Error al crear usuario', 'error');
      }
    });
  }

  _showEditUserModal(userId) {
    const user = this.users.find(u => u.id === userId);
    if (!user) return;

    const content = `
      <div class="flex justify-between items-center mb-6">
        <h3 class="font-headline text-xl font-bold">Editar Usuario</h3>
        <button onclick="app.viewManager.hideModal()" class="text-on-surface-variant hover:text-on-surface">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>
      <form id="edit-user-form" class="space-y-4">
        <div>
          <label class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-1">Nombre</label>
          <input type="text" id="edit-user-name" value="${user.name}" class="w-full px-4 py-3 rounded-xl bg-surface-container-low border border-surface-container focus:outline-none focus:border-primary/50 font-medium" required>
        </div>
        <div>
          <label class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-1">Usuario</label>
          <input type="text" id="edit-user-username" value="${user.username}" class="w-full px-4 py-3 rounded-xl bg-surface-container-low border border-surface-container focus:outline-none focus:border-primary/50 font-medium" required>
        </div>
        <div>
          <label class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-1">Contraseña (dejar vacío para mantener)</label>
          <input type="password" id="edit-user-password" placeholder="••••••••" class="w-full px-4 py-3 rounded-xl bg-surface-container-low border border-surface-container focus:outline-none focus:border-primary/50 font-medium" minlength="6">
        </div>
        <div>
          <label class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-1">Rol</label>
          <select id="edit-user-role" class="w-full px-4 py-3 rounded-xl bg-surface-container-low border border-surface-container focus:outline-none focus:border-primary/50 font-medium">
            <option value="waiter" ${user.role === 'waiter' ? 'selected' : ''}>Garzón</option>
            <option value="chef" ${user.role === 'chef' ? 'selected' : ''}>Chef</option>
            <option value="admin" ${user.role === 'admin' ? 'selected' : ''}>Admin</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-1">Estación</label>
          <input type="text" id="edit-user-station" value="${user.station || ''}" class="w-full px-4 py-3 rounded-xl bg-surface-container-low border border-surface-container focus:outline-none focus:border-primary/50 font-medium">
        </div>
        <button type="submit" class="w-full btn-gradient py-4 text-on-primary font-bold rounded-xl shadow-lg active:scale-95 transition-transform flex justify-center items-center gap-2 mt-2">
          <i data-lucide="save" class="w-5 h-5"></i>
          GUARDAR CAMBIOS
        </button>
      </form>
    `;

    app.viewManager.showModal(content);

    document.getElementById('edit-user-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const userData = {
        name: document.getElementById('edit-user-name').value,
        username: document.getElementById('edit-user-username').value,
        role: document.getElementById('edit-user-role').value,
        station: document.getElementById('edit-user-station').value
      };

      const password = document.getElementById('edit-user-password').value;
      if (password) userData.password = password;

      try {
        const result = await this.api.editUser(userId, userData);
        if (result.success) {
          this.toast.show(`Usuario ${result.data.name} actualizado`);
          app.viewManager.hideModal();
          await this.loadData();
        } else {
          this.toast.show(result.error || 'Error al editar usuario', 'error');
        }
      } catch (error) {
        this.toast.show('Error al editar usuario', 'error');
      }
    });
  }

  formatCurrency(amount) {
    return new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(amount);
  }
}

window.AdminView = AdminView;