import { AxiosAuth } from '@/kernel/services/axios/index.js';
import EndpointNamespaces from '@/kernel/services/api-client/native/endpoint_namespaces/index.js';

const colibriAPI = function () {
    return Object.assign({
        namespace: '',
        payloadData: {},
        getParams: {},
        response: {},
        onUploadProgress: null,
        headers: {},
        __makeRequest: function(endpointUrl, method) {
            if (typeof AxiosAuth[method] === 'function') {
                const config = {
                    url: `${this.namespace}/${endpointUrl}`,
                    method: method,
                    headers: this.headers,
                    onUploadProgress: this.onUploadProgress,
                    data: this.payloadData,
                    params: this.getParams
                };

                this.response = AxiosAuth.request(config);

                return this.response;
            }

            else{
                throw new Error(`Invalid method: ${method}`);
            }
        },
        sendTo: function (endpointUrl) {
            return this.__makeRequest(endpointUrl, 'post');
        },
        getFrom: function (endpointUrl) {
            return this.__makeRequest(endpointUrl, 'get');
        },
        putTo: function (endpointUrl) {
            return this.__makeRequest(endpointUrl, 'put');
        },
        delete: function (endpointUrl) {
            return this.__makeRequest(endpointUrl, 'delete');
        },
        with: function (payloadData) {
            this.payloadData = payloadData;

            return this;
        },
        uploadProgress: function (callback) {
            this.onUploadProgress = callback;

            return this;
        },
        params: function (getParams) {
            this.getParams = getParams;

            return this;
        },
        withHeaders: function(headers) {
            this.headers = headers;

            return this;
        } 
    }, EndpointNamespaces);
}

export { colibriAPI };