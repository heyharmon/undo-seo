import axios from 'axios';

const api = axios.create({
  baseURL: '/api',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest'
  },
  withCredentials: true // Required for CSRF cookie to be sent with requests
});

// Request interceptor for adding auth token
api.interceptors.request.use(config => {
  const token = localStorage.getItem('token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Response interceptor for handling errors
api.interceptors.response.use(
  response => response.data,
  error => {
    if (error.response) {
      // Handle unauthorized errors
      if (error.response.status === 401) {
        localStorage.removeItem('token');
        localStorage.removeItem('user');
        // If using a router, you could redirect to login here
        // window.location.href = '/login';
      }
      
      // Return the error response data for better error handling
      return Promise.reject(error.response.data);
    }
    return Promise.reject(error);
  }
);

export default api;
