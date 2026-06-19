class App {
  constructor() {
    this.api = new ApiClient('http://localhost:8000');
    this.toast = new ToastManager();
    this.viewManager = new ViewManager();
    this.currentUser = null;

    this.waiterView = null;
    this.kitchenView = null;
    this.adminView = null;

    this._bindLoginEvents();
    this._bindLogoutEvent();
    this._bindNavEvents();
    this._bindModalEvents();
    this._updateTime();

    this._restoreSession();

    setInterval(() => this._updateTime(), 1000);
  }

  _bindLoginEvents() {
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
      loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        await this._handleLogin();
      });
    }
  }

  _bindLogoutEvent() {
    const btnLogout = document.getElementById('btn-logout');
    if (btnLogout) {
      btnLogout.addEventListener('click', () => this._handleLogout());
    }
  }

  _bindNavEvents() {
    const navWaiter = document.getElementById('nav-waiter');
    const navKitchen = document.getElementById('nav-kitchen');
    const navAdmin = document.getElementById('nav-admin');

    if (navWaiter) navWaiter.addEventListener('click', () => this._switchToView('waiter'));
    if (navKitchen) navKitchen.addEventListener('click', () => this._switchToView('kitchen'));
    if (navAdmin) navAdmin.addEventListener('click', () => this._switchToView('admin'));
  }

  _bindModalEvents() {
    const modalContainer = document.getElementById('modal-container');
    if (modalContainer) {
      modalContainer.addEventListener('click', (e) => {
        if (e.target === modalContainer) {
          this.viewManager.hideModal();
        }
      });
    }
  }

  async _handleLogin() {
    const username = document.getElementById('username').value.toLowerCase().trim();
    const password = document.getElementById('password').value;
    const errorEl = document.getElementById('login-error');
    const errorTextEl = document.getElementById('login-error-text');
    const btnLogin = document.getElementById('btn-login');

    btnLogin.disabled = true;
    btnLogin.innerHTML = '<div class="loading-spinner"></div> Iniciando...';

    try {
      const result = await this.api.login(username, password);

      if (result.success) {
        this.currentUser = result.user;
        const token = result.user.id;
        localStorage.setItem('auth_token', token);
        this.api.setAuthToken(token);

        if (errorEl) errorEl.classList.add('hidden');

        this.viewManager.showApp();
        this.viewManager.applyRolePermissions(this.currentUser.role);
        this.viewManager.updateHeader(this.currentUser.name, this.currentUser.role);
        this.viewManager.updateStation(this.currentUser.station || '');

        this._initializeViews();

        const defaultView = this._getDefaultViewForRole(this.currentUser.role);
        this.viewManager.switchView(defaultView);

        // Cargar datos después de renderizar la vista
        setTimeout(() => this._refreshCurrentView(), 100);

        this.toast.show(`Bienvenido, ${this.currentUser.name}`);
      } else {
        if (errorEl) errorEl.classList.remove('hidden');
        if (errorTextEl) errorTextEl.innerText = result.error || 'Credenciales inválidas';

        const form = document.getElementById('login-form');
        form.classList.add('translate-x-2');
        setTimeout(() => form.classList.remove('translate-x-2'), 100);
      }
    } catch (error) {
      console.error('Login error:', error);
      if (errorEl) errorEl.classList.remove('hidden');
      if (errorTextEl) errorTextEl.innerText = 'Error de conexión';
    } finally {
      btnLogin.disabled = false;
      btnLogin.innerHTML = '<i data-lucide="log-in" class="w-5 h-5"></i> INICIAR SESIÓN';
      lucide.createIcons();
    }
  }

  _getDefaultViewForRole(role) {
    const viewMap = {
      waiter: 'waiter',
      chef: 'kitchen',
      admin: 'waiter'
    };
    return viewMap[role] || 'waiter';
  }

  _handleLogout() {
    this.currentUser = null;
    this.api.clearAuthToken();
    localStorage.removeItem('auth_token');
    document.getElementById('login-form').reset();
    this.viewManager.hideApp();
    this.toast.show('Sesión cerrada', 'info');
  }

  async _restoreSession() {
    const savedToken = localStorage.getItem('auth_token');
    if (savedToken) {
      try {
        const result = await this.api.getSession(savedToken);
        
        // Bypass temporal de prueba si el backend aún no tiene implementado getSession
        if (!result || !result.success) {
            console.warn("Usando sesión local simulada temporalmente");
            // Reconstruye un objeto simulado basado en el último rol que usas (ej: waiter)
            this.currentUser = { id: savedToken, name: 'Garzón', role: 'waiter', station: 'Salón 1' };
            this.api.setAuthToken(savedToken);
            this.viewManager.showApp();
            this.viewManager.applyRolePermissions(this.currentUser.role);
            this.viewManager.updateHeader(this.currentUser.name, this.currentUser.role);
            this._initializeViews();
            this.viewManager.switchView('waiter');
            setTimeout(() => this._refreshCurrentView(), 100);
            return;
        }

        if (result && result.success) {
          this.currentUser = result.user;
          this.api.setAuthToken(savedToken);
          this.viewManager.showApp();
          this.viewManager.applyRolePermissions(this.currentUser.role);
          this.viewManager.updateHeader(this.currentUser.name, this.currentUser.role);
          this.viewManager.updateStation(this.currentUser.station || '');
          this._initializeViews();
          const defaultView = this._getDefaultViewForRole(this.currentUser.role);
          this.viewManager.switchView(defaultView);
          setTimeout(() => this._refreshCurrentView(), 100);
          return;
        }
      } catch (error) {
        console.error('Session restoration error:', error);
      }
    }
    localStorage.removeItem('auth_token');
    this.viewManager.hideApp();
  }

  _initializeViews() {
    this.waiterView = new WaiterView(this.api, this.toast);
    this.kitchenView = new KitchenView(this.api, this.toast);
    this.adminView = new AdminView(this.api, this.toast);
  }

  async _switchToView(viewName) {
    this.viewManager.switchView(viewName);
    await this._refreshCurrentView();
  }

  async _refreshCurrentView() {
    const views = document.querySelectorAll('.view');
    for (const view of views) {
      if (!view.classList.contains('hidden')) {
        const viewId = view.id.replace('view-', '');

        if (viewId === 'waiter' && this.waiterView) {
          await this.waiterView.loadData();
        } else if (viewId === 'kitchen' && this.kitchenView) {
          await this.kitchenView.loadData();
          this.kitchenView.renderKDS();
        } else if (viewId === 'admin' && this.adminView) {
          await this.adminView.loadData();
        }
        break;
      }
    }
  }

  _updateTime() {
    const timeEl = document.getElementById('current-time');
    if (timeEl) {
      const now = new Date();
      timeEl.innerText = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
  }
}

const app = new App();
window.app = app;