const axiosMock = {
  create: () => axiosMock,
  get: () => Promise.resolve({ data: {} }),
  post: () => Promise.resolve({ data: {} }),
  put: () => Promise.resolve({ data: {} }),
  patch: () => Promise.resolve({ data: {} }),
  delete: () => Promise.resolve({ data: {} }),
  defaults: { headers: { common: {} } },
  interceptors: {
    request: { use: () => 0, eject: () => {} },
    response: { use: () => 0, eject: () => {} },
  },
};

module.exports = axiosMock;
module.exports.default = axiosMock;
