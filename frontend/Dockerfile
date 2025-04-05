FROM node:16-alpine

WORKDIR /app

COPY package*.json ./

RUN npm install

COPY . .

ENV PATH /app/node_modules/.bin:$PATH

CMD ["npm", "run", "build"] 