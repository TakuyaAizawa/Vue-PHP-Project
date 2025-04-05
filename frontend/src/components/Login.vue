<template>
  <div class="auth-container">
    <h2>ログイン</h2>
    <form @submit.prevent="login" class="auth-form">
      <div class="form-group">
        <label for="email">メールアドレス</label>
        <input 
          type="email" 
          id="email" 
          v-model="email" 
          required
          placeholder="example@example.com"
        />
      </div>
      <div class="form-group">
        <label for="password">パスワード</label>
        <input 
          type="password" 
          id="password" 
          v-model="password" 
          required
          placeholder="パスワード"
        />
      </div>
      <div v-if="error" class="error-message">
        {{ error }}
      </div>
      <button type="submit" :disabled="loading">
        {{ loading ? 'ログイン中...' : 'ログイン' }}
      </button>
      <p class="toggle-auth">
        アカウントをお持ちでない方は <a href="#" @click.prevent="$emit('toggle-auth')">新規登録</a>
      </p>
    </form>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'LoginComponent',
  data() {
    return {
      email: '',
      password: '',
      error: '',
      loading: false
    };
  },
  methods: {
    async login() {
      this.error = '';
      this.loading = true;
      
      try {
        const response = await axios.post('/api/login', {
          email: this.email,
          password: this.password
        });
        
        // ユーザー情報とトークンを保存
        localStorage.setItem('token', response.data.token);
        localStorage.setItem('user', JSON.stringify(response.data.user));
        
        // 親コンポーネントにログイン成功を通知
        this.$emit('login-success', response.data.user);
      } catch (error) {
        if (error.response && error.response.data && error.response.data.error) {
          this.error = error.response.data.error;
        } else {
          this.error = 'ログイン処理中にエラーが発生しました。';
        }
        console.error('Login error:', error);
      } finally {
        this.loading = false;
      }
    }
  }
};
</script>

<style scoped>
.auth-container {
  max-width: 400px;
  margin: 0 auto;
  padding: 20px;
  background-color: white;
  border-radius: 8px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

h2 {
  text-align: center;
  margin-bottom: 20px;
  color: #333;
}

.auth-form {
  display: flex;
  flex-direction: column;
}

.form-group {
  margin-bottom: 15px;
}

label {
  display: block;
  margin-bottom: 5px;
  font-weight: bold;
  color: #555;
}

input {
  width: 100%;
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 16px;
}

button {
  padding: 12px;
  background-color: #4caf50;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 16px;
  margin-top: 10px;
}

button:hover {
  background-color: #388e3c;
}

button:disabled {
  background-color: #9e9e9e;
  cursor: not-allowed;
}

.error-message {
  color: #f44336;
  margin: 10px 0;
  text-align: center;
}

.toggle-auth {
  margin-top: 15px;
  text-align: center;
  font-size: 14px;
}

.toggle-auth a {
  color: #2196f3;
  text-decoration: none;
}

.toggle-auth a:hover {
  text-decoration: underline;
}
</style> 