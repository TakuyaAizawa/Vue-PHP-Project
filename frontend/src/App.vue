<template>
  <div class="app-container">
    <header class="app-header">
      <h1>TODOアプリ</h1>
      <div v-if="isLoggedIn" class="user-info">
        <span>{{ user.name }} さん</span>
        <button @click="logout" class="logout-btn">ログアウト</button>
      </div>
    </header>

    <main>
      <!-- 認証済みの場合はTODOリストを表示 -->
      <div v-if="isLoggedIn">
        <TodoList 
          :user="user" 
          @auth-error="handleAuthError"
        />
      </div>
      <!-- 未認証の場合はログイン/登録フォームを表示 -->
      <div v-else>
        <Login 
          v-if="showLogin" 
          @login-success="handleAuthSuccess" 
          @toggle-auth="showLogin = false"
        />
        <Register 
          v-else 
          @register-success="handleAuthSuccess" 
          @toggle-auth="showLogin = true"
        />
      </div>
    </main>
  </div>
</template>

<script>
import axios from 'axios';
import Login from './components/Login.vue';
import Register from './components/Register.vue';
import TodoList from './components/TodoList.vue';

export default {
  name: 'App',
  components: {
    Login,
    Register,
    TodoList
  },
  data() {
    return {
      user: null,
      isLoggedIn: false,
      showLogin: true
    };
  },
  mounted() {
    // ページロード時に認証状態を確認
    this.checkAuth();
  },
  methods: {
    async checkAuth() {
      const token = localStorage.getItem('token');
      const storedUser = localStorage.getItem('user');
      
      if (token && storedUser) {
        try {
          // トークンの検証
          const response = await axios.get('/api/me', {
            headers: {
              'Authorization': `Bearer ${token}`
            }
          });
          
          this.user = response.data.user;
          this.isLoggedIn = true;
        } catch (error) {
          console.error('認証エラー:', error);
          this.logout();
        }
      }
    },
    handleAuthSuccess(user) {
      this.user = user;
      this.isLoggedIn = true;
    },
    handleAuthError() {
      // 認証エラーが発生した場合はログアウト
      this.logout();
    },
    logout() {
      localStorage.removeItem('token');
      localStorage.removeItem('user');
      this.user = null;
      this.isLoggedIn = false;
      this.showLogin = true;
    }
  }
};
</script>

<style>
body {
  font-family: 'Arial', sans-serif;
  background-color: #f5f5f5;
  margin: 0;
  padding: 0;
}

.app-container {
  max-width: 800px;
  margin: 0 auto;
  padding: 20px;
}

.app-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
}

h1 {
  color: #333;
  margin: 0;
}

.user-info {
  display: flex;
  align-items: center;
}

.user-info span {
  margin-right: 15px;
  font-weight: bold;
}

.logout-btn {
  background-color: #f44336;
  color: white;
  border: none;
  border-radius: 4px;
  padding: 8px 12px;
  cursor: pointer;
  font-size: 14px;
}

.logout-btn:hover {
  background-color: #d32f2f;
}

main {
  margin-top: 20px;
}
</style> 