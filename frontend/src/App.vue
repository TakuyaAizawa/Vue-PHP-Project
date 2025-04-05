<template>
  <div class="container">
    <h1>TODOアプリ</h1>
    <div class="add-todo">
      <input 
        v-model="newTodo" 
        @keyup.enter="addTodo" 
        placeholder="新しいタスクを入力" 
        type="text" 
      />
      <button @click="addTodo">追加</button>
    </div>
    <div class="todos-container">
      <div v-if="todos.length === 0" class="empty-state">
        タスクがありません。新しいタスクを追加してください。
      </div>
      <ul v-else class="todo-list">
        <li v-for="todo in todos" :key="todo.id" class="todo-item">
          <div class="todo-content">
            <input 
              type="checkbox" 
              :checked="todo.completed" 
              @change="toggleComplete(todo)"
            />
            <span :class="{ completed: todo.completed }">{{ todo.text }}</span>
          </div>
          <button @click="deleteTodo(todo)" class="delete-btn">削除</button>
        </li>
      </ul>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'App',
  data() {
    return {
      todos: [],
      newTodo: '',
      apiUrl: '/api/index.php'
    };
  },
  mounted() {
    this.fetchTodos();
  },
  methods: {
    async fetchTodos() {
      try {
        const response = await axios.get(this.apiUrl);
        this.todos = response.data;
      } catch (error) {
        console.error('Error fetching todos:', error);
      }
    },
    async addTodo() {
      if (!this.newTodo.trim()) return;
      
      try {
        const response = await axios.post(this.apiUrl, { text: this.newTodo });
        this.todos.push(response.data);
        this.newTodo = '';
      } catch (error) {
        console.error('Error adding todo:', error);
      }
    },
    async toggleComplete(todo) {
      try {
        await axios.put(this.apiUrl, {
          id: todo.id,
          completed: !todo.completed
        });
        
        todo.completed = !todo.completed;
      } catch (error) {
        console.error('Error updating todo:', error);
      }
    },
    async deleteTodo(todo) {
      try {
        await axios.delete(this.apiUrl, { data: { id: todo.id } });
        this.todos = this.todos.filter(t => t.id !== todo.id);
      } catch (error) {
        console.error('Error deleting todo:', error);
      }
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

.container {
  max-width: 600px;
  margin: 50px auto;
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
  padding: 20px;
}

h1 {
  text-align: center;
  color: #333;
  margin-bottom: 20px;
}

.add-todo {
  display: flex;
  margin-bottom: 20px;
}

input[type="text"] {
  flex: 1;
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 4px 0 0 4px;
  font-size: 16px;
}

button {
  padding: 10px 20px;
  background-color: #4caf50;
  color: white;
  border: none;
  border-radius: 0 4px 4px 0;
  cursor: pointer;
  font-size: 16px;
  transition: background-color 0.3s;
}

button:hover {
  background-color: #388e3c;
}

.todo-list {
  list-style: none;
  padding: 0;
}

.todo-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px;
  border-bottom: 1px solid #eee;
}

.todo-content {
  display: flex;
  align-items: center;
}

.todo-content input[type="checkbox"] {
  margin-right: 10px;
}

.completed {
  text-decoration: line-through;
  color: #888;
}

.delete-btn {
  background-color: #f44336;
  border-radius: 4px;
  padding: 6px 12px;
}

.delete-btn:hover {
  background-color: #d32f2f;
}

.empty-state {
  text-align: center;
  color: #888;
  padding: 20px;
}
</style> 