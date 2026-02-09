import { useState, useEffect } from 'react';
import { ApiService } from '../services/ApiService';

interface FormData {
    amount: number;
}


export default function DepositForm () {

    const [formData, setFormData] = useState<FormData>({ amount: 0 });
    const [depositSuccess, setDepositSuccess] = useState<boolean>(false);
    const apiService: ApiService = new ApiService();

    useEffect(() => {
        if (depositSuccess) {
            console.log('Form submitted successfully!');
        }
    }, [depositSuccess]);


    const handleChange = (event: any) => {
        const { name, value } = event.target;
        setFormData ( (previousFormData) => ({ ...previousFormData, [name]: value}) )
    }

    const handleForm = (event: any) => {
        apiService.sendDeposit(formData.amount).then(
            (r: any) => {
                if (!r.ok) {
                    throw new Error(`HTTP error! Status: ${r.status}`);
                }
                setDepositSuccess(true);
            }
        )
    }


    return (
        <div>
            <div className="row mb-3">
                <div className="col-md-12">
                    <div className="form-floating mb-3 mb-md-0">
                        <input type="text" name="amount" id="amount" className="form-control" value={formData.amount} onChange={handleChange} />
                        <label htmlFor="amount" className="form-label">Amount</label>
                    </div>
                </div>
            </div>
            <div className="row mb-3">
                <div className="col-md-12">
                    <button type="button" className="btn btn-primary" onClick={handleForm}>Send deposit</button>
                </div>
            </div>
        </div>
    );
}
